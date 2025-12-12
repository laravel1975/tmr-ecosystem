<?php

namespace TmrEcosystem\Sales\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Logistics\Domain\Events\DeliveryNoteUpdated;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderItemModel;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;

class UpdateSalesOrderStatusOnDelivery implements ShouldQueue
{
    use InteractsWithQueue;

    // Retry configuration
    public $tries = 3;
    public $backoff = 10;

    public function handle(DeliveryNoteUpdated $event): void
    {
        $deliveryNote = $event->deliveryNote;

        // 1. ทำงานเฉพาะเมื่อสถานะเป็น Delivered เท่านั้น
        if ($deliveryNote->status !== 'delivered') {
            return;
        }

        Log::info("Sales: Delivery Note {$deliveryNote->delivery_number} delivered. Updating shipped quantities...");

        DB::transaction(function () use ($deliveryNote) {

            // 2. ดึงรายการสินค้าที่ถูกจัดส่งจริง (ผ่าน Picking Slip)
            // ต้อง Join ข้าม Schema (Logistics -> Sales) ใน Monolith ทำได้
            // แต่ควรระวัง Query Performance

            $shippedItems = DB::table('logistics_picking_slip_items')
                ->where('picking_slip_id', $deliveryNote->picking_slip_id)
                ->get();

            if ($shippedItems->isEmpty()) {
                Log::warning("Sales: No items found for Delivery Note {$deliveryNote->delivery_number}");
                return;
            }

            // 3. อัปเดตยอด Shipped ใน Sales Order Items
            foreach ($shippedItems as $item) {
                // Increment qty_shipped แบบ Atomic
                SalesOrderItemModel::where('id', $item->sales_order_item_id)
                    ->increment('qty_shipped', $item->quantity_picked);
            }

            // 4. ตรวจสอบสถานะภาพรวมของ Order
            $order = SalesOrderModel::with('items')->find($deliveryNote->order_id);

            if (!$order) {
                Log::error("Sales: Order ID {$deliveryNote->order_id} not found.");
                return;
            }

            // เช็คว่า "ทุกรายการ" ส่งครบแล้วหรือยัง?
            $isFullyShipped = $order->items->every(function ($item) {
                return $item->qty_shipped >= $item->quantity;
            });

            // เช็คว่า "มีการส่งบ้างแล้ว" หรือไม่?
            $hasSomeShipment = $order->items->sum('qty_shipped') > 0;

            // 5. อัปเดตสถานะ Order
            $newStatus = null;

            if ($isFullyShipped) {
                // ถ้าส่งครบแล้ว ถือว่าจบงาน (หรือรอ Invoice)
                $newStatus = OrderStatus::Completed->value;
                Log::info("Sales: Order {$order->order_number} is FULLY SHIPPED -> Marked as COMPLETED.");
            } elseif ($hasSomeShipment) {
                $newStatus = OrderStatus::PartiallyShipped->value;
                Log::info("Sales: Order {$order->order_number} is PARTIALLY SHIPPED -> Status updated.");
            }

            if ($newStatus && $newStatus !== $order->status) {
                $order->update(['status' => $newStatus]);
            }
        });
    }
}
