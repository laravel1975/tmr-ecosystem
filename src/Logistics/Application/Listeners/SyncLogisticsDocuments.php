<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

// Services
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Services\StockPickingService;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;

class SyncLogisticsDocuments implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    public function handle(OrderUpdated $event): void
    {
        $orderAggregate = $event->order;
        Log::info("Logistics: Syncing documents for Updated Order: {$orderAggregate->getOrderNumber()}");

        $orderModel = SalesOrderModel::where('id', $orderAggregate->getId())->first();
        if (!$orderModel) return;

        DB::transaction(function () use ($orderModel, $orderAggregate) {
            $warehouseId = $orderModel->warehouse_id ?? 'DEFAULT_WAREHOUSE';
            $companyId = $orderModel->company_id;

            // 1. ค้นหา Picking Slip ที่ยังแก้ไขได้ (รวม 'ready', 'partial' ด้วย!)
            $picking = PickingSlip::with('items')
                ->where('order_id', $orderModel->id)
                ->whereIn('status', ['ready', 'partial', 'pending', 'assigned'])
                ->orderBy('created_at', 'desc') // เอาใบจองล่าสุด
                ->first();

            if ($picking) {
                // === STEP A: คืนสต็อกเก่า (Rollback Reservation) ===
                foreach ($picking->items as $oldItem) {
                    $inventoryItem = $this->itemLookupService->findByPartNumber($oldItem->product_id);
                    if ($inventoryItem && $oldItem->quantity_requested > 0) {
                        // เนื่องจากเราไม่ได้เก็บ Location ไว้ใน PickingItem (ในเวอร์ชั่นก่อน)
                        // เราต้องพยายามคืน โดยหาว่าจองไว้ที่ไหน (Best Effort)
                        // หรือถ้าในอนาคตเก็บ location_id ให้ใช้ตรงนี้

                        // วิธีแก้ขัด: คืนเข้า General หรือ ค้นหา StockLevel ที่มี Soft Reserve
                        $stocks = $this->stockRepo->findWithSoftReserve($inventoryItem->uuid, $warehouseId);

                        $qtyToRelease = $oldItem->quantity_requested;
                        foreach ($stocks as $stock) {
                            if ($qtyToRelease <= 0) break;
                            $releaseAmount = min($qtyToRelease, $stock->getQuantitySoftReserved());

                            $stock->releaseSoftReservation($releaseAmount);
                            $this->stockRepo->save($stock, []);

                            $qtyToRelease -= $releaseAmount;
                        }
                        Log::info("Stock: Released {$oldItem->quantity_requested} for {$oldItem->product_id}");
                    }
                }

                // === STEP B: ลบรายการเก่า ===
                $picking->items()->delete();

                // === STEP C: คำนวณและจองใหม่ (Re-Allocate) ===
                $hasShortage = false;

                foreach ($orderAggregate->getItems() as $item) {
                    $inventoryItem = $this->itemLookupService->findByPartNumber($item->productId);
                    if (!$inventoryItem) continue;

                    // คำนวณแผนการหยิบใหม่
                    $plan = $this->pickingService->calculatePickingPlan(
                        $inventoryItem->uuid,
                        $warehouseId,
                        (float) $item->quantity
                    );

                    $qtyAllocated = 0;

                    foreach ($plan as $step) {
                        $locationUuid = $step['location_uuid'];
                        $qtyToPick = $step['quantity'];

                        if (!$locationUuid) {
                            $hasShortage = true;
                            continue;
                        }

                        try {
                            $stockLevel = $this->stockRepo->findByLocation(
                                $inventoryItem->uuid, $locationUuid, $companyId
                            );

                            if ($stockLevel) {
                                $stockLevel->reserveSoft($qtyToPick); // จองใหม่
                                $this->stockRepo->save($stockLevel, []);
                                $qtyAllocated += $qtyToPick;

                                // สร้าง Item ใหม่
                                $line = new PickingSlipItem();
                                $line->picking_slip_id = $picking->id;
                                $line->sales_order_item_id = $this->findSalesOrderItemId($orderModel->id, $item->productId);
                                $line->product_id = $item->productId;
                                $line->quantity_requested = $qtyToPick;
                                $line->quantity_picked = 0;
                                $line->save();
                            }
                        } catch (InsufficientStockException $e) {
                            $hasShortage = true;
                        }
                    }

                    if ($qtyAllocated < $item->quantity) $hasShortage = true;
                }

                // อัปเดตสถานะ Picking Slip และ Order
                $picking->update([
                    'status' => $hasShortage ? 'partial' : 'pending',
                    'note' => trim($picking->note . "\n[System] Updated: รายการเปลี่ยนแปลงเมื่อ " . now()),
                ]);

                $orderModel->update([
                    'stock_status' => $hasShortage ? 'backorder' : 'reserved'
                ]);

                Log::info("Logistics: Successfully synced Picking Slip {$picking->picking_number}");

            } else {
                 Log::warning("Logistics: No editable Picking Slip found for Updated Order {$orderAggregate->getOrderNumber()}");
            }

            // 2. Reset Delivery Note ถ้ามี
            $delivery = DeliveryNote::where('order_id', $orderModel->id)->first();
            if ($delivery && !in_array($delivery->status, ['shipped', 'delivered'])) {
                $delivery->update(['status' => 'wait_operation']);
            }
        });
    }

    private function findSalesOrderItemId($orderId, $productId)
    {
        return DB::table('sales_order_items')
            ->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->value('id');
    }
}
