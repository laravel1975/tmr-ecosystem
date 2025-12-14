<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use TmrEcosystem\Stock\Domain\Events\StockReceived;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;

class AllocateBackorders implements ShouldQueue
{
    use InteractsWithQueue;

    // ตั้งค่า Queue ให้ทำงานเบื้องหลัง เพื่อไม่ให้หน้าจอรับของ (Receive) หน่วง
    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService
    ) {}

    public function handle(StockReceived $event): void
    {
        Log::info("Backorder Allocation Started for Item: {$event->itemUuid}, Qty: {$event->quantity}");

        // 1. แปลง Item UUID เป็น Product ID (Part Number) เพื่อใช้ค้นหาใน Sales Order
        $inventoryItem = $this->itemLookupService->findByUuid($event->itemUuid);
        if (!$inventoryItem) {
            Log::warning("Item not found for allocation: {$event->itemUuid}");
            return;
        }
        $partNumber = $inventoryItem->partNumber;

        // 2. ค้นหา Sales Order ที่มีสถานะ Backorder และต้องการสินค้านี้
        // เรียงตามลำดับมาก่อนได้ก่อน (FIFO)
        $backorders = SalesOrderModel::query()
            ->where('stock_status', 'backorder')
            ->where('company_id', $event->companyId)
            ->whereHas('items', function ($q) use ($partNumber) {
                $q->where('product_id', $partNumber)
                  ->whereRaw('quantity > qty_shipped'); // เฉพาะรายการที่ยังส่งไม่ครบ
            })
            ->orderBy('created_at', 'asc') // FIFO Priority
            ->with(['items' => function ($q) use ($partNumber) {
                $q->where('product_id', $partNumber);
            }])
            ->get();

        if ($backorders->isEmpty()) {
            return; // ไม่มีใครรอสินค้านี้ จบการทำงาน
        }

        // ตัวแปรนับจำนวนคงเหลือของล็อตที่เพิ่งรับเข้ามา (Available to Allocate)
        $remainingStockFromEvent = $event->quantity;

        foreach ($backorders as $order) {
            if ($remainingStockFromEvent <= 0) break; // ของหมดแล้ว หยุดแจก

            DB::transaction(function () use ($order, $partNumber, $event, &$remainingStockFromEvent) {

                // ล็อค Order เพื่อป้องกัน Race Condition
                $orderLocked = SalesOrderModel::where('id', $order->id)->lockForUpdate()->first();
                $targetItem = $orderLocked->items->firstWhere('product_id', $partNumber);

                if (!$targetItem) return;

                // 3. คำนวณยอดที่ใบนี้ยังขาด (Needed)
                // สมมติ: สั่ง 10 ส่งไปแล้ว 4 -> ขาด 6
                $qtyNeeded = $targetItem->quantity - $targetItem->qty_shipped;

                // อย่าลืมหักยอดที่อาจจะอยู่ใน Picking Slip อื่นที่ยังไม่จบ (In-Transit Picking)
                $pendingPickingQty = PickingSlipItem::whereHas('pickingSlip', function($q) use ($order) {
                        $q->where('order_id', $order->id)
                          ->whereIn('status', ['pending', 'assigned', 'in_progress']);
                    })
                    ->where('product_id', $partNumber)
                    ->sum('quantity_requested');

                $realNeeded = max(0, $qtyNeeded - $pendingPickingQty);

                if ($realNeeded <= 0) return; // ใบนี้มี Picking Slip รอหยิบอยู่แล้ว ข้ามไป

                // 4. ตัดยอดที่จะให้ (Allocated)
                // ให้ได้เท่าที่มีใน Event หรือเท่าที่ขาด (Min)
                $qtyToAllocate = min($remainingStockFromEvent, $realNeeded);

                // 5. ทำการจอง Stock (Soft Reserve) ที่ Location นั้น
                // เราจองเจาะจงที่ Location ที่รับของเข้าเลย (ตาม Event) เพื่อความแม่นยำ
                try {
                    $stockLevel = $this->stockRepo->findByLocation(
                        $event->itemUuid,
                        $event->locationUuid,
                        $event->companyId
                    );

                    if ($stockLevel) {
                        $stockLevel->reserveSoft($qtyToAllocate);
                        $this->stockRepo->save($stockLevel, []);

                        // 6. สร้าง Picking Slip ใบใหม่ (สำหรับยอด Backorder นี้)
                        $this->createBackorderPickingSlip($orderLocked, $targetItem, $qtyToAllocate);

                        // ตัดยอดคงเหลือของ Event
                        $remainingStockFromEvent -= $qtyToAllocate;

                        Log::info("Allocated {$qtyToAllocate} {$partNumber} to Order {$orderLocked->order_number}");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to allocate backorder for {$orderLocked->order_number}: " . $e->getMessage());
                }

                // ตรวจสอบว่า Order นี้ได้ของครบทุกรายการหรือยัง ถ้าครบแล้วเปลี่ยนสถานะ
                // (Logic นี้อาจต้องเช็คสินค้าตัวอื่นด้วย ในที่นี้ขอละไว้เพื่อความกระชับ)
                // if (checkAllItemsFulfilled($orderLocked)) $orderLocked->update(['stock_status' => 'reserved']);
            });
        }
    }

    /**
     * Helper Function: สร้างเอกสาร Picking Slip ใหม่
     */
    private function createBackorderPickingSlip($order, $item, $qty)
    {
        $picking = PickingSlip::create([
            'picking_number' => 'PK-' . date('Ymd') . '-BO-' . strtoupper(Str::random(4)),
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'warehouse_id' => $order->warehouse_id,
            'status' => 'pending',
            'note' => 'Auto-generated from Backorder Allocation'
        ]);

        PickingSlipItem::create([
            'picking_slip_id' => $picking->id,
            'sales_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'quantity_requested' => $qty,
            'quantity_picked' => 0
        ]);

        DeliveryNote::create([
            'delivery_number' => 'DO-' . date('Ymd') . '-BO-' . strtoupper(Str::random(4)),
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'picking_slip_id' => $picking->id,
            'shipping_address' => $order->shipping_address ?? 'N/A',
            'status' => 'wait_operation'
        ]);
    }
}
