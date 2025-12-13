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
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;

class OrderFulfillmentService
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    /**
     * Main Function: จัดสรรสินค้าและสร้างเอกสาร Logistics
     */
    public function fulfillOrder(string $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            // 1. Lock Order
            $orderModel = SalesOrderModel::where('id', $orderId)->lockForUpdate()->first();

            if (!$orderModel || $orderModel->status === 'cancelled') {
                Log::info("Fulfillment: Order {$orderId} is invalid or cancelled.");
                return;
            }

            // ตรวจสอบว่าเคยสร้างเอกสารไปแล้วหรือไม่ (Idempotency)
            if (PickingSlip::where('order_id', $orderId)->where('status', '!=', 'cancelled')->exists()) {
                Log::info("Fulfillment: Order {$orderId} already has active picking slips. Skipping.");
                return;
            }

            $warehouseId = $orderModel->warehouse_id ?? 'DEFAULT_WAREHOUSE';
            $companyId = $orderModel->company_id;
            $itemsToCreate = [];
            $orderFullyFulfilled = true;

            foreach ($orderModel->items as $item) {
                // Logic การคำนวณและจอง (เหมือนเดิม แต่ย้ายมาไว้ที่นี่)
                $qtyNeeded = $item->quantity; // เริ่มต้นคือต้องการเต็มจำนวนเพราะเช็คแล้วว่ายังไม่มีใบหยิบ

                $inventoryItem = $this->itemLookupService->findByPartNumber($item->product_id);
                if (!$inventoryItem) {
                    $orderFullyFulfilled = false;
                    continue;
                }

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
                        $stockLevel = $this->stockRepo->findByLocation($inventoryItem->uuid, $locationUuid, $companyId);

                        // Pre-check
                        if ($stockLevel && $stockLevel->getAvailableQuantity() >= $qtyToPick) {
                            $stockLevel->reserveSoft($qtyToPick);
                            $this->stockRepo->save($stockLevel, []);

                            $qtyAllocatedThisRound += $qtyToPick;
                            $itemsToCreate[] = [
                                'product_id' => $item->product_id,
                                'sales_order_item_id' => $item->id,
                                'quantity' => $qtyToPick,
                            ];
                        }
                    } catch (Exception $e) {
                        Log::warning("Fulfillment: Error reserving stock at {$locationUuid}: " . $e->getMessage());
                    }
                }

                if ($qtyAllocatedThisRound < $qtyNeeded) {
                    $orderFullyFulfilled = false;
                }
            }

            // สร้างเอกสาร
            if (count($itemsToCreate) > 0) {
                $this->createDocuments($orderModel, $itemsToCreate);
            }

            // Update Sales Order Status
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
