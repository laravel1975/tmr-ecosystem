<?php

namespace TmrEcosystem\Manufacturing\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Manufacturing\Application\UseCases\CreateProductionOrderUseCase;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use TmrEcosystem\Stock\Application\Contracts\StockCheckServiceInterface;

class CreateProductionOrderFromSales implements ShouldQueue
{
    public function __construct(
        protected CreateProductionOrderUseCase $createProductionOrderUseCase,
        protected StockCheckServiceInterface $stockCheckService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        // ✅ FIX 1: ดึงข้อมูลจาก Snapshot DTO แทนการใช้ Entity Order โดยตรง
        // เพราะ Event OrderConfirmed ส่งมาแค่ ID และ Snapshot
        $snapshot = $event->orderSnapshot;

        Log::info("Manufacturing: Checking requirements for Order: {$snapshot->orderNumber}");

        // ✅ FIX 2: Loop ผ่าน items ใน Snapshot (เป็น Array of DTOs)
        foreach ($snapshot->items as $itemDto) {

            $productId = $itemDto->productId;
            $quantity = $itemDto->quantity;

            // ตรวจสอบข้อมูลสินค้า (ว่าเป็นสินค้าผลิตหรือไม่)
            // หมายเหตุ: ใช้ find() โดยสมมติว่า productId คือ PK หรือ UUID ที่ถูกต้อง
            $item = ItemModel::find($productId);

            // เงื่อนไข 1: ต้องเป็นสินค้าผลิต (is_manufactured)
            if ($item && $item->is_manufactured) {

                try {
                    // ✅ [เพิ่ม] Logic เช็คสต็อก (Stock Check)
                    // ดึง Warehouse ID จาก Snapshot
                    $warehouseId = $snapshot->warehouseId ?? 'DEFAULT_WAREHOUSE';

                    // เช็คยอดพร้อมขาย (ATP) ผ่าน Service
                    // หมายเหตุ: ตรวจสอบว่า getAvailableQuantity รับ parameter ชื่ออะไร (itemId หรือ itemUuid)
                    $availableQty = $this->stockCheckService->getAvailableQuantity(
                        itemId: $item->uuid,
                        warehouseId: $warehouseId
                    );

                    // คำนวณยอดที่ขาด (Net Requirement)
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
                                'origin_uuid' => $snapshot->orderId, // ✅ ใช้ orderId จาก Snapshot
                            ],
                            (string) $snapshot->companyId, // ✅ ใช้ companyId จาก Snapshot
                            'SYSTEM'
                        );

                        Log::info("Created Production Order for Item: {$item->part_number} (Qty: {$missingQty})");
                    } else {
                        Log::info("Manufacturing: Stock sufficient for {$item->part_number}. No PO needed (Need: {$neededQty}, Have: {$availableQty}).");
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to create MTO for Sales Order {$snapshot->orderNumber}: " . $e->getMessage());
                }
            }
        }
    }
}
