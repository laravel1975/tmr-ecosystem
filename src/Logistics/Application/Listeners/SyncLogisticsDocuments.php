<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Services\StockPickingService;
use TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel;
use TmrEcosystem\Logistics\Domain\Events\PickingSlipUpdated;

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
        $orderId = is_string($event->orderId) ? $event->orderId : ($event->orderId->id ?? null);
        if (!$orderId) return;

        Log::info("SyncLogistics: Processing updates for Order {$orderId}");

        DB::transaction(function () use ($orderId) {
            $order = SalesOrderModel::with('items')->lockForUpdate()->find($orderId);

            // หา Picking Slip ที่ยังแก้ไขได้ (เฉพาะสถานะ pending หรือ assigned)
            $pickingSlip = PickingSlip::with('items')
                                      ->where('order_id', $orderId)
                                      ->whereIn('status', ['pending', 'assigned'])
                                      ->first();

            if (!$pickingSlip) {
                Log::info("SyncLogistics: No modifiable picking slip found. Skipping.");
                return;
            }

            $warehouseId = $pickingSlip->warehouse_id
                ?? $order->warehouse_id
                ?? WarehouseModel::where('company_id', $order->company_id)->value('uuid');

            if (!$warehouseId) {
                Log::error("SyncLogistics: Cannot determine warehouse. Aborting.");
                return;
            }

            $salesItems = $order->items->keyBy('product_id');
            $pickingItems = $pickingSlip->items->keyBy('product_id');
            $hasChanges = false;

            // ---------------------------------------------------------
            // 1. ตรวจสอบการ "ลบ" หรือ "ลดจำนวน" (Picking > Sales)
            // ---------------------------------------------------------
            foreach ($pickingSlip->items as $pItem) {
                $productId = $pItem->product_id;

                if (!$salesItems->has($productId)) {
                    // กรณี A: ลบรายการทิ้ง
                    $this->releaseStock($pItem, $pItem->quantity_requested, $warehouseId);
                    $pItem->delete();
                    $hasChanges = true;
                    Log::info("SyncLogistics: Removed item {$productId}");
                } else {
                    // กรณี B: ลดจำนวน
                    $sItem = $salesItems->get($productId);
                    $diff = $sItem->quantity - $pItem->quantity_requested;

                    if ($diff < 0) { // Sales น้อยกว่า Picking
                        $qtyToRelease = abs($diff);
                        $this->releaseStock($pItem, $qtyToRelease, $warehouseId);

                        $pItem->quantity_requested = $sItem->quantity;
                        $pItem->save();
                        $hasChanges = true;
                        Log::info("SyncLogistics: Reduced qty for {$productId} by {$qtyToRelease}");
                    }
                }
            }

            // ---------------------------------------------------------
            // 2. ตรวจสอบการ "เพิ่ม" หรือ "สินค้าใหม่" (Sales > Picking)
            // ---------------------------------------------------------
            foreach ($order->items as $sItem) {
                $productId = $sItem->product_id;

                if (!$pickingItems->has($productId)) {
                    // กรณี C: สินค้าใหม่มาเพิ่ม
                    if ($this->allocateNewItem($pickingSlip, $sItem, $warehouseId)) {
                        $hasChanges = true;
                        Log::info("SyncLogistics: Added new item {$productId}");
                    }
                } else {
                    // กรณี D: เพิ่มจำนวน
                    $pItem = $pickingItems->get($productId);
                    $diff = $sItem->quantity - $pItem->quantity_requested;

                    if ($diff > 0) { // Sales มากกว่า Picking
                        $reserved = $this->allocateMoreStock($pItem, $diff, $warehouseId);
                        if ($reserved > 0) {
                            $pItem->quantity_requested += $reserved;
                            $pItem->save();
                            $hasChanges = true;
                            Log::info("SyncLogistics: Increased qty for {$productId} by {$reserved}");
                        }
                    }
                }
            }

            // ถ้ามีการเปลี่ยนแปลง ให้ส่ง Event เพื่อไปอัพเดท Delivery Note ต่อ
            if ($hasChanges) {
                if ($pickingSlip->items()->count() == 0) {
                    $pickingSlip->update(['status' => 'cancelled', 'note' => 'Auto cancelled (Empty items)']);
                } else {
                    $pickingSlip->touch(); // Update updated_at timestamp
                    event(new PickingSlipUpdated($pickingSlip));
                    Log::info("SyncLogistics: PickingSlip updated, triggered delivery note sync.");
                }
            }
        });
    }

    /**
     * ✅ [IMPLEMENTED] คืนสต๊อกจริง (Release Soft Reserve)
     */
    private function releaseStock($pickingItem, $qtyToRelease, $warehouseUuid)
    {
        $inventoryItem = $this->itemLookupService->findByPartNumber($pickingItem->product_id);
        if (!$inventoryItem) return;

        // ดึง StockLevel ที่มียอดจอง (Soft Reserve) ของสินค้านี้ ในคลังนี้
        $reservedStocks = $this->stockRepo->findWithSoftReserve($inventoryItem->uuid, $warehouseUuid);
        $remaining = $qtyToRelease;

        foreach ($reservedStocks as $stock) {
            if ($remaining <= 0) break;

            $availableInLoc = $stock->getQuantitySoftReserved();
            if ($availableInLoc > 0) {
                $amount = min($availableInLoc, $remaining);
                $stock->releaseSoftReservation($amount);
                $this->stockRepo->save($stock, []);
                $remaining -= $amount;
            }
        }
    }

    private function allocateMoreStock($pickingItem, $qtyNeeded, $warehouseUuid)
    {
        $inventoryItem = $this->itemLookupService->findByPartNumber($pickingItem->product_id);
        if (!$inventoryItem) return 0;

        $plan = $this->pickingService->calculatePickingPlan($inventoryItem->uuid, $warehouseUuid, $qtyNeeded);
        $totalReserved = 0;

        foreach ($plan as $step) {
            $stock = $this->stockRepo->findByLocation($inventoryItem->uuid, $step['location_uuid'], $pickingItem->company_id);
            if ($stock) {
                $stock->reserveSoft($step['quantity']);
                $this->stockRepo->save($stock, []);
                $totalReserved += $step['quantity'];
            }
        }
        return $totalReserved;
    }

    private function allocateNewItem($pickingSlip, $salesItem, $warehouseUuid)
    {
        // สร้าง Temporary Object เพื่อใช้ส่งข้อมูล
        $tempItem = new PickingSlipItem();
        $tempItem->product_id = $salesItem->product_id;
        $tempItem->company_id = $pickingSlip->company_id;

        $reserved = $this->allocateMoreStock($tempItem, $salesItem->quantity, $warehouseUuid);

        if ($reserved > 0) {
            PickingSlipItem::create([
                'picking_slip_id' => $pickingSlip->id,
                'sales_order_item_id' => $salesItem->id,
                'product_id' => $salesItem->product_id,
                'quantity_requested' => $reserved,
                'quantity_picked' => 0
            ]);
            return true;
        }
        return false;
    }
}
