<?php

namespace TmrEcosystem\Stock\Application\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockLevelModel;
use Exception;

class StockReservationService
{
    public function __construct(
        private StockLevelRepositoryInterface $repository
    ) {}

    /**
     * จองสินค้า (Soft) เมื่อ Order Confirmed
     */
    public function reserveForOrder(string $itemUuid, string $warehouseUuid, float $quantity, string $orderId): bool
    {
        return DB::transaction(function () use ($itemUuid, $warehouseUuid, $quantity, $orderId) {
            // Strategy: FIFO หรือ LIFO หรือ Specific Location ก็ได้
            // ในที่นี้สมมติว่าจองแบบ Global Warehouse Pool ก่อน (ยังไม่ระบุ Location)

            // หา Stock ทั้งหมดใน Warehouse นี้
            $stocks = $this->repository->findWithAvailableStock($itemUuid, $warehouseUuid);

            $remainingToReserve = $quantity;

            foreach ($stocks as $stock) {
                if ($remainingToReserve <= 0) break;

                $available = $stock->getAvailableQuantity();

                if ($available > 0) {
                    $amountToTake = min($available, $remainingToReserve);

                    // Domain Logic
                    $stock->reserveSoft($amountToTake, $orderId, new DateTimeImmutable('+1 day')); // TTL 24 Hours

                    // Persist
                    $this->repository->save($stock);

                    $remainingToReserve -= $amountToTake;
                }
            }

            if ($remainingToReserve > 0) {
                // กรณีของไม่พอ:
                // 1. Throw Exception เพื่อ Rollback Sales Order (Strict)
                // 2. หรือ Allow Partial Reservation (ขึ้นอยู่กับ Business)
                throw new Exception("Insufficient stock to reserve for Order {$orderId}. Missing: {$remainingToReserve}");
            }

            return true;
        });
    }

    /**
     * เปลี่ยนเป็น Hard Reserve (ตอนสร้าง Picking Slip หรือ Assign Picker)
     */
    public function confirmPickingAllocation(string $pickingSlipId, array $allocations): void
    {
        DB::transaction(function () use ($allocations) {
            foreach ($allocations as $allocation) {
                // $allocation = ['stock_uuid' => '...', 'quantity' => 10]
                $stock = $this->repository->findById($allocation['stock_uuid']);

                if (!$stock) continue;

                // Convert Soft -> Hard
                $stock->commitToHardReserve($allocation['quantity']);
                $this->repository->save($stock);
            }
        });
    }
}
