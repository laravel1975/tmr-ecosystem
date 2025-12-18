<?php

namespace TmrEcosystem\Stock\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;

class ReleaseStockUseCase
{
    public function __construct(
        private StockLevelRepositoryInterface $stockLevelRepository
    ) {}

    public function handle(string $referenceId, array $items, string $warehouseId): void
    {
        Log::info("ReleaseStockUseCase: Releasing stock for Ref ID {$referenceId}", ['warehouse' => $warehouseId]);

        DB::transaction(function () use ($items, $warehouseId) {
            foreach ($items as $item) {
                $itemId = $item['product_id'];
                $qtyToRelease = (float) $item['quantity'];

                if ($qtyToRelease <= 0) continue;

                // 1. ค้นหา StockLevel ที่มีการจอง Soft Reserve ค้างอยู่สำหรับสินค้านี้ในคลังนี้
                // (เนื่องจากเราไม่ได้ระบุเจาะจงว่าจองจากกองไหน เราจึงต้องหาจากกองที่มีการจองอยู่)
                $stockLevels = $this->stockLevelRepository->findWithSoftReserve($itemId, $warehouseId);

                $remainingToRelease = $qtyToRelease;

                foreach ($stockLevels as $stockLevel) {
                    if ($remainingToRelease <= 0) break;

                    // ดึงยอดที่จองอยู่ในกองนี้
                    $currentReserved = $stockLevel->getQuantitySoftReserved();

                    // คำนวณยอดที่จะปลดออกจากกองนี้ (ไม่เกินที่มีอยู่ และไม่เกินที่ต้องการปลด)
                    $amountToDeduct = min($currentReserved, $remainingToRelease);

                    if ($amountToDeduct > 0) {
                        // 2. เรียก Domain Logic เพื่อลด Soft Reserve
                        $stockLevel->releaseSoftReservation($amountToDeduct);

                        // 3. บันทึก (Repository ควร handle การ save movements ถ้ามี แต่ method นี้แค่เปลี่ยน state ภายใน)
                        // หมายเหตุ: การแก้ Soft Reserve มักไม่บันทึก Movement Log เพราะของไม่ได้ขยับ
                        // แต่ถ้าต้องการ Log ต้องทำผ่าน method ที่ return StockMovement
                        $this->stockLevelRepository->save($stockLevel, []);

                        $remainingToRelease -= $amountToDeduct;
                    }
                }

                if ($remainingToRelease > 0) {
                    Log::warning("ReleaseStockUseCase: Could not release full amount for item {$itemId}. Missing: {$remainingToRelease}");
                }
            }
        });
    }
}
