<?php

namespace TmrEcosystem\Inventory\Application\Services;

use Illuminate\Support\Facades\DB;
use TmrEcosystem\Inventory\Domain\Aggregates\StockReservation;
use TmrEcosystem\Inventory\Domain\Repositories\StockReservationRepositoryInterface;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Shared\Domain\Enums\ReservationState;
use Exception;

class HardReserveService
{
    public function __construct(
        private StockReservationRepositoryInterface $reservationRepo,
        private StockLevelRepositoryInterface $stockLevelRepo
    ) {}

    /**
     * Flow 3: Soft -> Hard Reserve (Promotion)
     * เกิดขึ้นเมื่อ Sales Order ยืนยันการชำระเงิน หรือ Confirm Order
     */
    public function promoteOrderToHardReserve(string $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            // 1. ดึง Reservation ทั้งหมดของ Order นี้
            $reservations = $this->reservationRepo->findByReferenceId($orderId);

            if ($reservations->isEmpty()) {
                // ถ้าไม่มี Reservation เลย อาจจะเป็น Order ที่ไม่มีสินค้าต้องจอง หรือข้อมูลผิดพลาด
                return;
            }

            foreach ($reservations as $reservation) {
                // Skip ถ้าไม่ใช่ Soft Reserve (เช่น อาจจะ Hard ไปแล้ว หรือ Released)
                if ($reservation->getState() !== ReservationState::SOFT_RESERVED) {
                    continue;
                }

                // 2. Domain Logic: Change State at Entity Level
                $reservation->promoteToHard();

                // 3. Update Inventory StockLevel (Aggregate Interaction)
                $stockLevel = $this->stockLevelRepo->findByItemAndWarehouse(
                    $reservation->getInventoryItemId(),
                    $reservation->getWarehouseId()
                );

                if (!$stockLevel) {
                    throw new Exception("Stock level record missing for reservation {$reservation->getId()}. Data inconsistency.");
                }

                // สั่ง StockLevel ย้ายยอดจาก Soft -> Hard (Atomic Operation)
                $stockLevel->convertSoftToHard($reservation->getQuantity());

                // 4. Persist Changes
                $this->reservationRepo->save($reservation);
                $this->stockLevelRepo->save($stockLevel);
            }
        });
    }

    /**
     * Flow 1: Create Soft Reserve (Intent)
     * เกิดขึ้นเมื่อสร้าง Sales Order (Draft/Created)
     */
    public function createSoftReservation(string $orderId, array $items, string $warehouseId): void
    {
        DB::transaction(function () use ($orderId, $items, $warehouseId) {
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = (float) $item['quantity'];

                // ค้นหา StockLevel
                $stockLevel = $this->stockLevelRepo->findByItemAndWarehouse($productId, $warehouseId);

                // Validation: ต้องมี Record ใน Inventory
                if (!$stockLevel) {
                    throw new Exception("Stock record not found for item {$productId} in warehouse {$warehouseId}");
                }

                // Check Availability using Domain Logic
                if ($stockLevel->getAvailableQuantity() < $quantity) {
                    throw new Exception("Insufficient stock for item {$productId}. Requested: {$quantity}, Available: " . $stockLevel->getAvailableQuantity());
                }

                // Create Reservation Entity (Inventory BC)
                $reservation = StockReservation::createSoft(
                    $productId,
                    $warehouseId,
                    $orderId,
                    $quantity
                );

                // Update StockLevel to reflect reservation (Stock BC)
                $stockLevel->increaseSoftReserve($quantity);

                // Persist both
                $this->reservationRepo->save($reservation);
                $this->stockLevelRepo->save($stockLevel);
            }
        });
    }
}
