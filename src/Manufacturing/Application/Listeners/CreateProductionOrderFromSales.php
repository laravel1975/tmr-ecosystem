<?php

namespace TmrEcosystem\Manufacturing\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Manufacturing\Application\UseCases\CreateProductionOrderUseCase;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use TmrEcosystem\Sales\Domain\Entities\OrderItem;
// ✅ [เพิ่ม] Import Stock Service
use TmrEcosystem\Stock\Application\Contracts\StockCheckServiceInterface;

class CreateProductionOrderFromSales implements ShouldQueue
{
    public function __construct(
        protected CreateProductionOrderUseCase $createProductionOrderUseCase,
        // ✅ [เพิ่ม] Inject Stock Service เพื่อเช็คของก่อนสั่งผลิต
        protected StockCheckServiceInterface $stockCheckService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $salesOrder = $event->order;

        // ✅ FIX 1: ใช้ Getter Method (getOrderNumber)
        Log::info("Manufacturing: Checking requirements for Order: {$salesOrder->getOrderNumber()}");

        // ✅ FIX 2: ใช้ Getter Method (getItems)
        $orderItems = $salesOrder->getItems();

        foreach ($orderItems as $orderItem) {
            /** @var OrderItem $orderItem */

            $productId = $orderItem->productId; // หรือใช้ $orderItem->getProductId() ถ้ามี
            $quantity = $orderItem->quantity;

            // ตรวจสอบข้อมูลสินค้า (ว่าเป็นสินค้าผลิตหรือไม่)
            $item = ItemModel::find($productId);

            // เงื่อนไข 1: ต้องเป็นสินค้าผลิต (is_manufactured) หรือมี BOM
            if ($item && $item->is_manufactured) {

                try {
                    // ✅ [เพิ่ม] Logic เช็คสต็อก (Stock Check)
                    // ดึง Warehouse ID จาก Order (ถ้าไม่มีให้ใช้ Default)
                    $warehouseId = method_exists($salesOrder, 'getWarehouseId')
                        ? $salesOrder->getWarehouseId()
                        : 'DEFAULT_WAREHOUSE';

                    // เช็คยอดพร้อมขาย (ATP)
                    $availableQty = $this->stockCheckService->getAvailableQuantity(
                        itemId: $item->uuid, // ใช้ UUID ตามที่แก้ใน Service
                        warehouseId: $warehouseId
                    );

                    // คำนวณยอดที่ขาด
                    $neededQty = $quantity;
                    $missingQty = $neededQty - $availableQty;

                    // เงื่อนไข 2: สร้าง PO เฉพาะเมื่อของไม่พอ (Missing > 0)
                    if ($missingQty > 0) {

                        Log::info("Manufacturing: Stock insufficient for {$item->part_number}. Need {$neededQty}, Have {$availableQty}. Creating PO for {$missingQty}.");

                        // สร้างใบสั่งผลิต (ผลิตเฉพาะส่วนที่ขาด)
                        $this->createProductionOrderUseCase->execute(
                            [
                                'item_uuid' => $item->uuid,
                                'planned_quantity' => $missingQty, // ✅ ใช้ยอดที่ขาด
                                'planned_start_date' => now(),
                                'planned_end_date' => now()->addDays(7), // Default Lead time

                                // ระบุที่มาว่ามาจาก Sales Order ใบนี้
                                'origin_type' => 'sales_order',
                                'origin_uuid' => $salesOrder->getId(), // ✅ FIX 3: ใช้ getId()
                            ],
                            (string) $salesOrder->getCompanyId(),      // ✅ FIX 4: ใช้ getCompanyId()
                            'SYSTEM'
                        );

                        Log::info("Created Production Order for Item: {$item->part_number} (Qty: {$missingQty})");
                    } else {
                        Log::info("Manufacturing: Stock sufficient for {$item->part_number}. No PO needed.");
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to create MTO for Sales Order {$salesOrder->getOrderNumber()}: " . $e->getMessage());
                }
            }
        }
    }
}
