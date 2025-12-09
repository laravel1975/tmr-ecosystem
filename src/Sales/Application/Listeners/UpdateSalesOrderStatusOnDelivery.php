<?php

namespace TmrEcosystem\Sales\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Logistics\Domain\Events\DeliveryNoteUpdated; // สมมติว่ามี Event นี้เมื่อ update status
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class UpdateSalesOrderStatusOnDelivery implements ShouldQueue
{
    public function handle(DeliveryNoteUpdated $event): void
    {
        $deliveryNote = $event->deliveryNote;

        // สนใจเฉพาะตอนสถานะเป็น Delivered (ส่งของถึงมือลูกค้าแล้ว)
        if ($deliveryNote->status !== 'delivered') {
            return;
        }

        $order = SalesOrderModel::with('items')->find($deliveryNote->order_id);
        if (!$order) return;

        // คำนวณสถานะใหม่ของ Order
        $totalOrdered = $order->items->sum('quantity');
        $totalShipped = $order->items->sum('qty_shipped');

        if ($totalShipped >= $totalOrdered) {
            $order->update(['status' => 'completed']); // ส่งครบจบงาน
            Log::info("Sales Order {$order->order_number} marked as COMPLETED.");
        } else {
            $order->update(['status' => 'partially_shipped']); // ยังค้างส่ง
            Log::info("Sales Order {$order->order_number} marked as PARTIALLY SHIPPED.");
        }
    }
}
