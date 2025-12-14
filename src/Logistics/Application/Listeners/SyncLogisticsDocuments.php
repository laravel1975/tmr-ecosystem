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

        Log::info("SyncLogistics: Checking updates for Order {$orderId}");

        DB::transaction(function () use ($orderId) {
            $order = SalesOrderModel::with('items')->lockForUpdate()->find($orderId);

            // 1. หา Picking Slip ที่ยังแก้ไขได้ (เฉพาะสถานะ pending หรือ assigned)
            $pickingSlip = PickingSlip::with('items')
                                      ->where('order_id', $orderId)
                                      ->whereIn('status', ['pending', 'assigned'])
                                      ->first();

            if (!$pickingSlip) {
                Log::info("SyncLogistics: No modifiable picking slip found (Status might be picked/shipped). Skipping.");
                return;
            }

            // 2. ✅ แก้ไข: หา Warehouse ID ให้ชัวร์ (Picking -> Order -> Company Default)
            $warehouseId = $pickingSlip->warehouse_id
                ?? $order->warehouse_id
                ?? WarehouseModel::where('company_id', $order->company_id)->value('uuid');

            if (!$warehouseId) {
                Log::error("SyncLogistics: Unknown Warehouse for Order {$orderId}. Cannot sync stock.");
                return;
            }

            $salesItems = $order->items->keyBy('product_id');
            $pickingItems = $pickingSlip->items->keyBy('product_id');

            // --- PART A: Loop รายการใน Picking Slip (เช็ค ลบ หรือ ลดจำนวน) ---
            foreach ($pickingSlip->items as $pItem) {
                $productId = $pItem->product_id;

                if (!$salesItems->has($productId)) {
                    // กรณี: ลบรายการทิ้ง (Removed)
                    $this->releaseStock($pItem, $pItem->quantity_requested, $warehouseId);
                    $pItem->delete();
                    Log::info("SyncLogistics: Removed item {$productId}");
                } else {
                    // กรณี: ลดจำนวน (Reduced)
                    $sItem = $salesItems->get($productId);
                    $diff = $sItem->quantity - $pItem->quantity_requested; // ถ้าติดลบ แปลว่า Picking เยอะกว่า Order

                    if ($diff < 0) {
                        $qtyToRelease = abs($diff);
                        $this->releaseStock($pItem, $qtyToRelease, $warehouseId);

                        $pItem->quantity_requested = $sItem->quantity;
                        $pItem->save();
                        Log::info("SyncLogistics: Reduced qty for {$productId} by {$qtyToRelease}");
                    }
                }
            }

            // --- PART B: Loop รายการใน Sales Order (เช็ค เพิ่มใหม่ หรือ เพิ่มจำนวน) ---
            foreach ($order->items as $sItem) {
                $productId = $sItem->product_id;

                if (!$pickingItems->has($productId)) {
                    // กรณี: เพิ่มสินค้าใหม่ (New Item)
                    $this->allocateNewItem($pickingSlip, $sItem, $warehouseId);
                    Log::info("SyncLogistics: Added new item {$productId}");
                } else {
                    // กรณี: เพิ่มจำนวน (Increased)
                    $pItem = $pickingItems->get($productId);
                    $diff = $sItem->quantity - $pItem->quantity_requested;

                    if ($diff > 0) {
                        // จองเพิ่มเท่าที่ทำได้
                        $reserved = $this->allocateMoreStock($pItem, $diff, $warehouseId);

                        // อัปเดตยอด Picking Slip (เพิ่มเท่าที่จองได้)
                        // หมายเหตุ: ถ้าจองไม่ได้ (ของหมด) ยอดใน Picking จะไม่เพิ่มตาม SO ซึ่งถูกต้องแล้ว (เกิด Backorder โดยปริยาย)
                        if ($reserved > 0) {
                            $pItem->quantity_requested += $reserved;
                            $pItem->save();
                            Log::info("SyncLogistics: Increased qty for {$productId} by {$reserved}");
                        } else {
                            Log::warning("SyncLogistics: Failed to allocate more stock for {$productId} (Out of stock)");
                        }
                    }
                }
            }

            // ถ้าแก้ไขแล้วไม่เหลือสินค้าเลย ให้ยกเลิก Picking Slip
            if ($pickingSlip->items()->count() == 0) {
                $pickingSlip->update(['status' => 'cancelled', 'note' => 'Auto cancelled via Sync (Empty items)']);
            }
        });
    }

    /**
     * ✅ [FIXED] คืนสต๊อก (Release Soft Reserve)
     * วนหา StockLevel ที่มียอดจอง (Soft Reserve) แล้วคืนยอดกลับไป
     */
    private function releaseStock($pickingItem, $qtyToRelease, $warehouseUuid)
    {
        $inventoryItem = $this->itemLookupService->findByPartNumber($pickingItem->product_id);
        if (!$inventoryItem) return;

        // หา Stock ทั้งหมดของสินค้านี้ใน Warehouse นี้ที่มีการจอง
        $reservedStocks = $this->stockRepo->findWithSoftReserve($inventoryItem->uuid, $warehouseUuid);

        $remainingToRelease = $qtyToRelease;

        foreach ($reservedStocks as $stockLevel) {
            if ($remainingToRelease <= 0) break;

            $reservedInLoc = $stockLevel->getQuantitySoftReserved();

            if ($reservedInLoc > 0) {
                $amountToRelease = min($reservedInLoc, $remainingToRelease);

                $stockLevel->releaseSoftReservation($amountToRelease);
                $this->stockRepo->save($stockLevel, []);

                $remainingToRelease -= $amountToRelease;
            }
        }
    }

    /**
     * ✅ [FIXED] จองสต๊อกเพิ่ม
     */
    private function allocateMoreStock($pickingItem, $qtyNeeded, $warehouseUuid)
    {
        $inventoryItem = $this->itemLookupService->findByPartNumber($pickingItem->product_id);
        if (!$inventoryItem || !$warehouseUuid) return 0;

        // คำนวณแผนการหยิบ (Picking Plan) เพื่อหาว่ามีของที่ Location ไหนบ้าง
        $plan = $this->pickingService->calculatePickingPlan($inventoryItem->uuid, $warehouseUuid, $qtyNeeded);
        $totalReserved = 0;

        foreach ($plan as $step) {
            $stock = $this->stockRepo->findByLocation(
                $inventoryItem->uuid,
                $step['location_uuid'],
                $pickingItem->company_id ?? 1 // ใช้ Company ID จาก Picking Slip
            );

            if ($stock) {
                $stock->reserveSoft($step['quantity']);
                $this->stockRepo->save($stock, []);
                $totalReserved += $step['quantity'];
            }
        }
        return $totalReserved;
    }

    /**
     * ✅ [FIXED] สร้าง Picking Item ใหม่
     */
    private function allocateNewItem($pickingSlip, $salesItem, $warehouseUuid)
    {
        // สร้าง Dummy Object เพื่อส่งให้ฟังก์ชัน allocateMoreStock
        $tempPickingItem = new PickingSlipItem();
        $tempPickingItem->product_id = $salesItem->product_id;
        $tempPickingItem->company_id = $pickingSlip->company_id;

        $reserved = $this->allocateMoreStock(
            $tempPickingItem,
            $salesItem->quantity,
            $warehouseUuid
        );

        if ($reserved > 0) {
            PickingSlipItem::create([
                'picking_slip_id' => $pickingSlip->id,
                'sales_order_item_id' => $salesItem->id,
                'product_id' => $salesItem->product_id,
                'quantity_requested' => $reserved,
                'quantity_picked' => 0
            ]);
        }
    }
}
