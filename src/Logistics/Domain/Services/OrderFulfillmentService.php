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
    public function fulfillOrderFromSnapshot(OrderSnapshotDto $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            // 1. Create Picking Slip
            $pickingSlip = PickingSlip::create([
                'id' => (string) Str::uuid(),
                'picking_number' => 'PK-' . strtoupper(Str::random(10)), // Generate Picking Number
                'order_id' => $snapshot->orderId,
                'order_number' => $snapshot->orderNumber, // Assuming DTO has this
                'customer_id' => $snapshot->customerId,   // Assuming DTO has this
                'company_id' => $snapshot->companyId,
                'warehouse_id' => $snapshot->warehouseId,
                'status' => 'pending',
            ]);

            // 2. Create Items
            foreach ($snapshot->items as $item) {
                // Determine product ID and name based on available data
                $productId = $item->productId ?? $item->partNumber ?? 'UNKNOWN';
                $productName = $item->productName ?? 'Unknown Product';

                PickingSlipItem::create([
                    'picking_slip_id' => $pickingSlip->id,
                    'sales_order_item_id' => $item->id,
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'quantity_requested' => $item->quantity,
                    'quantity_picked' => 0,
                    'status' => 'pending'
                ]);
            }

            // 3. ✅ Create Delivery Note (Wait for picking)
            DeliveryNote::create([
                'id' => (string) Str::uuid(),
                'delivery_number' => 'DO-' . strtoupper(Str::random(10)), // Generate Delivery Number
                'order_id' => $snapshot->orderId,
                'picking_slip_id' => $pickingSlip->id,
                'company_id' => $snapshot->companyId,
                'shipping_address' => $snapshot->shippingAddress ?? 'See Order Details', // Fallback address
                'status' => 'wait_picking', // Initial status
            ]);

            Log::info("Logistics: Created Picking Slip #{$pickingSlip->picking_number} and Delivery Note for Order {$snapshot->orderNumber}.");
        });
    }

    /**
     * Main Function: จัดสรรสินค้าและสร้างเอกสาร Logistics (Legacy / Direct DB)
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
                            $stockLevel->reserveSoft($qtyToPick);
                            $this->stockRepo->save($stockLevel, []);

                            $qtyAllocatedThisRound += $qtyToPick;
                            $itemsToCreate[] = [
                                'product_id' => $item->product_id,
                                'sales_order_item_id' => $item->id,
                                'quantity' => $qtyToPick,
                            ];
                        }
                    } catch (InsufficientStockException $e) {
                        Log::warning("Fulfillment: Allocation skipped at {$locationUuid} - " . $e->getMessage());
                    } catch (Exception $e) {
                        Log::error("Fulfillment: System error reserving stock - " . $e->getMessage());
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

    /**
     * คำนวณแผนการหยิบสินค้า (Picking Plan) จากข้อมูล Order Snapshot
     * Helper Function สำหรับแปลง DTO ให้เป็น Array ที่พร้อม Save
     */
    private function calculatePickingPlan(OrderSnapshotDto $snapshot): array
    {
        $plan = [];

        foreach ($snapshot->items as $item) {
            // เช็คชื่อตัวแปรที่ส่งมาจาก DTO (รองรับทั้ง camelCase และ snake_case)
            $productId = $item->productId ?? $item->product_id ?? null;

            if (!$productId) {
                Log::warning("Logistics: Skipping item in Order {$snapshot->orderNumber} due to missing Product ID.");
                continue;
            }

            $plan[] = [
                'sales_order_item_id' => $item->id,
                'product_id'          => $productId,
                'product_name'        => $item->productName ?? $item->product_name ?? 'Unknown Product',
                'quantity_requested'  => $item->quantity,
                'quantity_picked'     => 0,
            ];
        }

        return $plan;
    }
}
