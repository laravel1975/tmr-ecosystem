<?php

namespace TmrEcosystem\Logistics\Domain\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Application\Services\StockPickingService;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;

class OrderFulfillmentService
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    /**
     * [Day 5] สร้างเอกสารจาก Snapshot DTO (Decoupled from Sales DB)
     */
    public function fulfillOrderFromSnapshot(OrderSnapshotDto $orderSnapshot): void
    {
        DB::transaction(function () use ($orderSnapshot) {

            // 1. สร้าง Picking Slip Header จากข้อมูลใน Event
            $pickingSlip = PickingSlip::create([
                'order_id' => $orderSnapshot->orderId, // ใช้ ID ที่ส่งมา
                'order_number' => $orderSnapshot->orderNumber,
                'customer_id' => $orderSnapshot->customerId, // Logistics อาจจะยังต้องเก็บ Ref นี้ไว้
                // [FIX] เพิ่ม Company ID (สำคัญมากสำหรับ Multi-tenant)
                'company_id' => $orderSnapshot->companyId,
                'warehouse_id' => $orderSnapshot->warehouseId,
                'picking_number' => 'PK-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                'status' => 'pending',
                'created_at' => now(),
            ]);

            // 2. สร้าง Picking Slip Items จาก Array ใน DTO
            foreach ($orderSnapshot->items as $itemDto) {
                PickingSlipItem::create([
                    'picking_slip_id' => $pickingSlip->id,
                    // Map ข้อมูลจาก DTO -> Logistics Schema
                    'sales_order_item_id' => $itemDto->productId, // หรือใช้ ID ของ Item ถ้ามีใน DTO
                    'product_name' => $itemDto->productName,
                    'quantity_requested' => $itemDto->quantity,
                    'quantity_picked' => 0, // เริ่มต้นยังไม่ได้หยิบ
                ]);
            }

            Log::info("Logistics: Created Picking Slip #{$pickingSlip->id} for Order {$orderSnapshot->orderNumber} using Event Snapshot.");
        });
    }

    /**
     * Main Function: จัดสรรสินค้าและสร้างเอกสาร Logistics
     */
    public function fulfillOrder(string $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            // Lock Order เพื่อป้องกัน Race Condition
            $orderModel = SalesOrderModel::where('id', $orderId)->lockForUpdate()->first();

            if (!$orderModel || $orderModel->status === 'cancelled') {
                Log::info("Fulfillment: Order {$orderId} is invalid or cancelled.");
                return;
            }

            // Check Idempotency (ป้องกันการสร้างซ้ำ)
            if (PickingSlip::where('order_id', $orderId)->where('status', '!=', 'cancelled')->exists()) {
                Log::info("Fulfillment: Order {$orderId} already has active picking slips. Skipping.");
                return;
            }

            $warehouseId = $orderModel->warehouse_id ?? 'DEFAULT_WAREHOUSE';
            $companyId = $orderModel->company_id;
            $itemsToCreate = [];
            $orderFullyFulfilled = true;

            foreach ($orderModel->items as $item) {
                // ยอดที่ต้องการจอง
                $qtyNeeded = $item->quantity;

                $inventoryItem = $this->itemLookupService->findByPartNumber($item->product_id);
                if (!$inventoryItem) {
                    $orderFullyFulfilled = false;
                    continue;
                }

                // คำนวณแผนการหยิบ (Picking Strategy)
                $plan = $this->pickingService->calculatePickingPlan(
                    $inventoryItem->uuid,
                    $warehouseId,
                    (float) $qtyNeeded
                );

                $qtyAllocatedThisRound = 0;

                foreach ($plan as $step) {
                    $locationUuid = $step['location_uuid'];
                    $qtyToPick = $step['quantity'];
                    if (is_null($locationUuid)) continue;

                    try {
                        $stockLevel = $this->stockRepo->findByLocation(
                            $inventoryItem->uuid,
                            $locationUuid,
                            $companyId
                        );

                        if ($stockLevel) {
                            // ✅ [REFACTORED] ลบ Pre-check (if available > qty) ออก
                            // ให้ Domain Model (StockLevel) เป็นคนตัดสินใจ
                            // ถ้าของไม่พอ มันจะ Throw InsufficientStockException เอง

                            $stockLevel->reserveSoft($qtyToPick);
                            $this->stockRepo->save($stockLevel, []); // อย่าลืม [] เพื่อแก้ ArgumentCountError

                            $qtyAllocatedThisRound += $qtyToPick;
                            $itemsToCreate[] = [
                                'product_id' => $item->product_id,
                                'sales_order_item_id' => $item->id,
                                'quantity' => $qtyToPick,
                            ];
                        }
                    } catch (InsufficientStockException $e) {
                        // Handle Business Logic: ของไม่พอใน Location นี้
                        // เราเลือกที่จะ "ข้าม" ไป Location ถัดไป หรือหยิบเท่าที่มี (ในที่นี้คือข้าม)
                        Log::warning("Fulfillment: Allocation skipped at {$locationUuid} - " . $e->getMessage());
                    } catch (Exception $e) {
                        // System Error (เช่น DB Connection)
                        Log::error("Fulfillment: System error reserving stock - " . $e->getMessage());
                        // กรณีนี้อาจเลือก throw เพื่อให้ Job Retry หรือ catch เพื่อข้ามรายการ
                    }
                }

                if ($qtyAllocatedThisRound < $qtyNeeded) {
                    $orderFullyFulfilled = false;
                }
            }

            // สร้างเอกสาร Picking Slip / Delivery Note
            if (count($itemsToCreate) > 0) {
                $this->createDocuments($orderModel, $itemsToCreate);
            }

            // อัปเดตสถานะ Stock ของ Order
            $orderModel->update([
                'stock_status' => $orderFullyFulfilled ? 'reserved' : 'backorder'
            ]);
        });
    }

    private function createDocuments(SalesOrderModel $order, array $items): void
    {
        $pickingSlip = PickingSlip::create([
            'picking_number' => 'PK-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'status' => 'pending'
        ]);

        foreach ($items as $data) {
            PickingSlipItem::create([
                'picking_slip_id' => $pickingSlip->id,
                'sales_order_item_id' => $data['sales_order_item_id'],
                'product_id' => $data['product_id'],
                'quantity_requested' => $data['quantity'],
                'quantity_picked' => 0
            ]);
        }

        DeliveryNote::create([
            'delivery_number' => 'DO-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'picking_slip_id' => $pickingSlip->id,
            'status' => 'waiting_picking',
            'shipping_address' => $order->shipping_address ?? 'Address N/A',
            'contact_person' => $order->contact_person ?? 'N/A'
        ]);

        Log::info("Fulfillment: Created documents for Order {$order->order_number}");
    }
}
