<?php

namespace TmrEcosystem\Sales\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Logistics\Domain\Events\DeliveryNoteUpdated;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;

class UpdateSalesOrderStatusOnDelivery implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DeliveryNoteUpdated $event): void
    {
        $deliveryNote = $event->deliveryNote;

        // 1. สนใจเฉพาะตอนสถานะเป็น Delivered (ส่งของถึงมือลูกค้าแล้ว)
        if ($deliveryNote->status !== 'delivered') {
            return;
        }

        Log::info("Sales: Delivery Note {$deliveryNote->delivery_number} delivered. Updating Order status...");

        $order = SalesOrderModel::with('items')->find($deliveryNote->order_id);

        if (!$order) {
            Log::warning("Sales: Order ID {$deliveryNote->order_id} not found for Delivery Note {$deliveryNote->delivery_number}");
            return;
        }

        // 2. ตรวจสอบสถานะ Order ปัจจุบัน (ถ้าจบไปแล้ว หรือยกเลิกไปแล้ว ไม่ต้องทำอะไร)
        if (in_array($order->status, [OrderStatus::Completed->value, OrderStatus::Cancelled->value])) {
            return;
        }

        // 3. คำนวณความคืบหน้า (Progress Calculation)
        // เช็คว่า "ทุกรายการ" ส่งครบแล้วหรือยัง?
        $isFullyShipped = $order->items->every(function ($item) {
            return $item->qty_shipped >= $item->quantity;
        });

        // เช็คว่า "มีการส่งบ้างแล้ว" หรือไม่?
        $hasSomeShipment = $order->items->sum('qty_shipped') > 0;

        // 4. ตัดสินใจเปลี่ยนสถานะ (State Transition Logic)
        $newStatus = null;

        if ($isFullyShipped) {
            $newStatus = OrderStatus::Completed->value;
            Log::info("Sales: Order {$order->order_number} is FULLY SHIPPED -> Marked as COMPLETED.");
        } elseif ($hasSomeShipment) {
            $newStatus = OrderStatus::PartiallyShipped->value;
            Log::info("Sales: Order {$order->order_number} is PARTIALLY SHIPPED -> Status updated.");
        }

        // 5. บันทึกผล
        if ($newStatus && $newStatus !== $order->status) {
            $order->update(['status' => $newStatus]);
        }
    }
}
