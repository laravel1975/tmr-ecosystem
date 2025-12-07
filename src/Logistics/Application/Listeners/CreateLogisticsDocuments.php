<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;

class CreateLogisticsDocuments implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderConfirmed $event): void
    {
        $orderAggregate = $event->order;
        Log::info("Logistics: Processing document creation for Order: {$orderAggregate->getOrderNumber()}");

        // ✅ FIX: เปลี่ยนจาก where('uuid', ...) เป็น where('id', ...)
        $orderModel = SalesOrderModel::where('id', $orderAggregate->getId())->first();

        if (!$orderModel) {
            Log::error("Logistics: Order not found in DB for ID {$orderAggregate->getId()}");
            return;
        }

        DB::transaction(function () use ($orderModel, $orderAggregate) {

            // 1. ตรวจสอบสถานะ Stock เพื่อกำหนดสถานะ Picking Slip
            $pickingStatus = 'ready'; // Default

            if (Schema::hasColumn('sales_orders', 'stock_status')) {
                if ($orderModel->stock_status === 'backorder') {
                    $pickingStatus = 'waiting_receipt';
                    Log::warning("Logistics: Order is BACKORDER. Setting Picking Slip to 'waiting_receipt'");
                }
            }

            // 2. Create Picking Slip
            $pickingSlip = new PickingSlip();
            $pickingSlip->picking_number = 'PK-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $pickingSlip->company_id = $orderModel->company_id;
            $pickingSlip->order_id = $orderModel->id; // ใช้ ID
            $pickingSlip->status = $pickingStatus;
            $pickingSlip->created_at = now();
            $pickingSlip->updated_at = now();
            $pickingSlip->save();

            // 2.2 สร้าง Items
            foreach ($orderAggregate->getItems() as $item) {
                $line = new PickingSlipItem();
                $line->picking_slip_id = $pickingSlip->id;

                $salesOrderItemId = DB::table('sales_order_items')
                    ->where('order_id', $orderModel->id)
                    ->where('product_id', $item->productId)
                    ->value('id');

                $line->sales_order_item_id = $salesOrderItemId;
                $line->product_id = $item->productId;
                $line->quantity_requested = $item->quantity;
                $line->quantity_picked = 0;
                $line->save();
            }

            // 3. Create Delivery Note
            $deliveryNote = new DeliveryNote();
            $deliveryNote->delivery_number = 'DO-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $deliveryNote->company_id = $orderModel->company_id;
            $deliveryNote->order_id = $orderModel->id;
            $deliveryNote->picking_slip_id = $pickingSlip->id;
            $deliveryNote->status = 'waiting_picking';
            $deliveryNote->shipping_address = 'N/A';
            $deliveryNote->contact_person = 'N/A';
            $deliveryNote->created_at = now();
            $deliveryNote->updated_at = now();
            $deliveryNote->save();

            Log::info("Logistics: Created Picking Slip {$pickingSlip->picking_number} [{$pickingStatus}] successfully.");
        });
    }
}
