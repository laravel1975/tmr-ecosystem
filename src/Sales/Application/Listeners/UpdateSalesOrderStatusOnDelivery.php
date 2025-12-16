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
// Import Contract & DTO
use TmrEcosystem\Sales\Application\Contracts\ShippedItemProviderInterface;

class UpdateSalesOrderStatusOnDelivery implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 3;
    public $backoff = 10;

    // [Refactor] Inject Service Interface
    public function __construct(
        private ShippedItemProviderInterface $shippedItemProvider
    ) {}

    public function handle(DeliveryNoteUpdated $event): void
    {
        $deliveryNote = $event->deliveryNote;

        if ($deliveryNote->status !== 'delivered') {
            return;
        }

        Log::info("Sales: Delivery Note {$deliveryNote->delivery_number} delivered. Processing fulfillment...");

        DB::transaction(function () use ($deliveryNote) {

            // [Refactor] 1. เรียกใช้ Service แทนการ Query ข้าม Schema โดยตรง
            // Sales ไม่จำเป็นต้องรู้ว่า Logistics เก็บข้อมูลยังไง รู้แค่ว่าได้ DTO กลับมา
            $shippedItems = $this->shippedItemProvider->getByPickingSlipId($deliveryNote->picking_slip_id);

            if (empty($shippedItems)) {
                Log::warning("Sales: No items found for Delivery Note {$deliveryNote->delivery_number}");
                return;
            }

            foreach ($shippedItems as $item) {
                // ... (Logic เดิม: Idempotency check จาก Day 1) ...
                $alreadyProcessed = DB::table('sales_fulfillment_histories')
                    ->where('sales_order_item_id', $item->sales_order_item_id)
                    ->where('delivery_note_id', $deliveryNote->id)
                    ->exists();

                if ($alreadyProcessed) {
                    continue;
                }

                // Update using DTO properties
                SalesOrderItemModel::where('id', $item->sales_order_item_id)
                    ->increment('qty_shipped', $item->quantity_picked);

                DB::table('sales_fulfillment_histories')->insert([
                    'sales_order_item_id' => $item->sales_order_item_id,
                    'delivery_note_id' => $deliveryNote->id,
                    'quantity_shipped' => $item->quantity_picked,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->updateSalesOrderStatus($deliveryNote->order_id);
        });
    }

    private function updateSalesOrderStatus(string $orderId): void
    {
        // ... (Logic เดิม ไม่เปลี่ยนแปลง) ...
        $order = SalesOrderModel::with('items')->find($orderId);
        if (!$order) return;

        $isFullyShipped = $order->items->every(fn($item) => $item->qty_shipped >= $item->quantity);
        $hasSomeShipment = $order->items->sum('qty_shipped') > 0;

        $newStatus = null;
        if ($isFullyShipped) {
            $newStatus = OrderStatus::Completed->value;
        } elseif ($hasSomeShipment) {
            $newStatus = OrderStatus::PartiallyShipped->value;
        }

        if ($newStatus && $newStatus !== $order->status) {
            $order->update(['status' => $newStatus]);
        }
    }
}
