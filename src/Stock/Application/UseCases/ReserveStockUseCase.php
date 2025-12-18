<?php

namespace TmrEcosystem\Stock\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;

class ReserveStockUseCase
{
    public function __construct(
        private StockLevelRepositoryInterface $stockLevelRepository
    ) {}

    public function handle(string $referenceId, array $items, string $warehouseId): void
    {
        // $items format: [['product_id' => 'uuid', 'quantity' => 5], ...]

        DB::transaction(function () use ($items, $warehouseId, $referenceId) {
            foreach ($items as $item) {
                $itemId = $item['product_id'];
                $qtyNeeded = (float) $item['quantity'];

                if ($qtyNeeded <= 0) continue;

                // 1. ดึงรายการสต็อกที่ "หยิบได้" โดยเรียงลำดับความสำคัญ (Picking > Bulk > General)
                // Repository จะจัดการเรื่อง Sort Order ให้แล้ว
                $availableStockLevels = $this->stockLevelRepository->findPickableStocks($itemId, $warehouseId);

                $qtyRemainingToReserve = $qtyNeeded;

                // 2. วนลูปจองสินค้าทีละกอง (Batch/Lot)
                foreach ($availableStockLevels as $stockLevel) {
                    if ($qtyRemainingToReserve <= 0) break;

                    // เช็คยอดที่ว่างจริงในกองนี้ (OnHand - Reserved - SoftReserved)
                    $availableInLocation = $stockLevel->getAvailableQuantity();

                    if ($availableInLocation <= 0) continue;

                    // คำนวณยอดที่จะจองจากกองนี้
                    $amountToReserve = min($availableInLocation, $qtyRemainingToReserve);

                    // สั่ง Domain Aggregate ให้จอง (Soft Reserve)
                    $stockLevel->reserveSoft($amountToReserve);

                    // บันทึกสถานะล่าสุดลง DB
                    // (ส่ง array ว่างไปใน movements เพราะการจองยังไม่ใช่การเคลื่อนย้ายสินค้าจริง)
                    $this->stockLevelRepository->save($stockLevel, []);

                    $qtyRemainingToReserve -= $amountToReserve;
                }

                // 3. ถ้าวนครบทุกกองแล้วยังได้ของไม่ครบ แสดงว่าของขาด
                if ($qtyRemainingToReserve > 0) {
                    Log::warning("ReserveStockUseCase: Stock insufficient for item {$itemId}. Needed: {$qtyNeeded}, Missing: {$qtyRemainingToReserve}");

                    throw new InsufficientStockException(
                        "สินค้าไม่เพียงพอสำหรับ Item ID: {$itemId} (ขาดอีก {$qtyRemainingToReserve} ชิ้น)"
                    );
                }
            }

            Log::info("ReserveStockUseCase: Successfully reserved stock for Ref {$referenceId}");
        });
    }
}
