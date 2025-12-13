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
        // รับ ID ให้ชัวร์
        $orderId = is_string($event->orderId) ? $event->orderId : ($event->orderId->id ?? null);
        if (!$orderId) return;

        Log::info("SyncLogistics: Checking updates for Order {$orderId}");

        DB::transaction(function () use ($orderId) {
            $order = SalesOrderModel::with('items')->lockForUpdate()->find($orderId);
            $pickingSlip = PickingSlip::with('items')->where('order_id', $orderId)
                                      ->whereIn('status', ['pending', 'assigned']) // แก้ได้เฉพาะสถานะที่ยังไม่จบ
                                      ->first();

            // ถ้าไม่มี Picking Slip หรือสถานะไปไกลแล้ว (Picked/Shipped) -> แก้ไม่ได้ ต้อง Cancel แล้วเปิดใหม่
            if (!$pickingSlip) {
                Log::info("SyncLogistics: No modifiable picking slip found. Skipping sync.");
                return;
            }

            // Map รายการสินค้า: Sales Order Items vs Picking Slip Items
            $salesItems = $order->items->keyBy('product_id');
            $pickingItems = $pickingSlip->items->keyBy('product_id');

            // 1. Loop เช็คของใน Picking Slip (เพื่อดูว่าต้อง ลด หรือ ลบ)
            foreach ($pickingSlip->items as $pItem) {
                $productId = $pItem->product_id;

                if (!$salesItems->has($productId)) {
                    // กรณี A: สินค้าถูกลบออกจาก Order -> ต้องคืน Stock และลบ Picking Item
                    $this->releaseStock($pItem, $pItem->quantity_requested);
                    $pItem->delete();
                    Log::info("SyncLogistics: Removed item {$productId}");
                } else {
                    // กรณี B: สินค้ายังอยู่ แต่จำนวนเปลี่ยน
                    $sItem = $salesItems->get($productId);
                    $diff = $sItem->quantity - $pItem->quantity_requested;

                    if ($diff < 0) {
                        // จำนวนลดลง -> คืน Stock ส่วนต่าง
                        $this->releaseStock($pItem, abs($diff));
                        $pItem->quantity_requested = $sItem->quantity;
                        $pItem->save();
                        Log::info("SyncLogistics: Reduced qty for {$productId} by " . abs($diff));
                    }
                }
            }

            // 2. Loop เช็คของใน Sales Order (เพื่อดูว่าต้อง เพิ่ม หรือไม่)
            foreach ($order->items as $sItem) {
                $productId = $sItem->product_id;

                if (!$pickingItems->has($productId)) {
                    // กรณี C: สินค้าใหม่เพิ่มเข้ามา -> ต้องจอง Stock เพิ่ม และสร้าง Picking Item
                    $this->allocateNewItem($pickingSlip, $sItem);
                    Log::info("SyncLogistics: Added new item {$productId}");
                } else {
                    // กรณี D: สินค้าเดิม แต่จำนวนเพิ่มขึ้น
                    $pItem = $pickingItems->get($productId);
                    $diff = $sItem->quantity - $pItem->quantity_requested;

                    if ($diff > 0) {
                        // จำนวนเพิ่ม -> จอง Stock เพิ่ม
                        $reserved = $this->allocateMoreStock($pItem, $diff, $order->warehouse_id ?? 'DEFAULT_WAREHOUSE');
                        if ($reserved > 0) {
                            $pItem->quantity_requested += $reserved;
                            $pItem->save();
                        }
                    }
                }
            }
        });
    }

    // Helper: คืนสต๊อก (Release)
    private function releaseStock($pickingItem, $qtyToRelease)
    {
        // ในระบบจริงต้องรู้ว่าจองจาก Location ไหน (ถ้าเก็บข้อมูลไว้)
        // แต่ถ้าไม่มี อาจต้องใช้ Logic FIFO Reverse หรือ Release จาก Location ที่มี Soft Reserve
        // เพื่อความง่ายในตัวอย่างนี้ สมมติว่าคืนเข้า Location แรกที่เจอใน Inventory
        // TODO: Implement precise location tracking in PickingSlipItem

        Log::warning("SyncLogistics: Releasing {$qtyToRelease} for {$pickingItem->product_id} (Location logic needed here)");
        // $stockLevel->releaseSoftReservation($qtyToRelease);
    }

    // Helper: จองเพิ่ม (Allocate More)
    private function allocateMoreStock($pickingItem, $qtyNeeded, $warehouseId)
    {
        $inventoryItem = $this->itemLookupService->findByPartNumber($pickingItem->product_id);
        if (!$inventoryItem) return 0;

        $plan = $this->pickingService->calculatePickingPlan($inventoryItem->uuid, $warehouseId, $qtyNeeded);
        $totalReserved = 0;

        foreach ($plan as $step) {
            $stock = $this->stockRepo->findByLocation($inventoryItem->uuid, $step['location_uuid'], $pickingItem->company_id ?? 1); // fix company_id
            if ($stock && $stock->getAvailableQuantity() >= $step['quantity']) {
                $stock->reserveSoft($step['quantity']);
                $this->stockRepo->save($stock, []);
                $totalReserved += $step['quantity'];
            }
        }
        return $totalReserved;
    }

    // Helper: จองรายการใหม่ (Allocate New)
    private function allocateNewItem($pickingSlip, $salesItem)
    {
        // ใช้ Logic เดียวกับ allocateMoreStock แล้ว create PickingSlipItem
        $reserved = $this->allocateMoreStock(
            (object)['product_id' => $salesItem->product_id, 'company_id' => $pickingSlip->company_id],
            $salesItem->quantity,
            'DEFAULT_WAREHOUSE'
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
