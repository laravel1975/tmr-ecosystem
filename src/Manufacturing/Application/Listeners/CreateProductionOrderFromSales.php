<?php

namespace TmrEcosystem\Manufacturing\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Manufacturing\Application\UseCases\CreateProductionOrderUseCase;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use TmrEcosystem\Sales\Domain\Entities\OrderItem;

class CreateProductionOrderFromSales implements ShouldQueue
{
    public function __construct(
        protected CreateProductionOrderUseCase $createProductionOrderUseCase
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $salesOrder = $event->order;

        // ✅ FIX 1: ใช้ Getter Method (getOrderNumber)
        Log::info("Processing MTO for Order: {$salesOrder->getOrderNumber()}");

        // ✅ FIX 2: ใช้ Getter Method (getItems)
        $orderItems = $salesOrder->getItems();

        foreach ($orderItems as $orderItem) {
            /** @var OrderItem $orderItem */

            // OrderItem เป็น Entity ที่มี public property อยู่แล้ว เรียกได้เลย
            $productId = $orderItem->productId;
            $quantity = $orderItem->quantity;

            // ตรวจสอบสินค้า
            $item = ItemModel::find($productId);

            // เงื่อนไข: สินค้าต้องมี BOM หรือเป็นสินค้าผลิต (is_manufactured)
            if ($item && $item->is_manufactured) {
                try {
                    // สร้างใบสั่งผลิต
                    $this->createProductionOrderUseCase->execute(
                        [
                            'item_uuid' => $item->uuid,
                            'planned_quantity' => $quantity,
                            'planned_start_date' => now(),
                            'planned_end_date' => now()->addDays(7),

                            // ระบุที่มาว่ามาจาก Sales Order ใบนี้
                            'origin_type' => 'sales_order',
                            'origin_uuid' => $salesOrder->getId(), // ✅ FIX 3: ใช้ getId()
                        ],
                        (string) $salesOrder->getCompanyId(),      // ✅ FIX 4: ใช้ getCompanyId()
                        'SYSTEM'
                    );

                    Log::info("Created Production Order for Item: {$item->part_number}");

                } catch (\Exception $e) {
                    Log::error("Failed to create MTO for Sales Order {$salesOrder->getOrderNumber()}: " . $e->getMessage());
                }
            }
        }
    }
}
