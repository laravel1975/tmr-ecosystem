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

            foreach ($reservations as $reservation) {
                // Skip ถ้าไม่ใช่ Soft Reserve
                if ($reservation->getState() !== ReservationState::SOFT_RESERVED) {
                    continue;
                }

                // 2. Domain Logic: Change State
                $previousState = $reservation->getState();
                $reservation->promoteToHard();

                // 3. Update Inventory StockLevel (Aggregate Interaction)
                // เราต้องย้ายยอดจาก field 'soft_reserved' ไป 'hard_reserved' ใน StockLevel ด้วย
                $stockLevel = $this->stockLevelRepo->findByItemAndWarehouse(
                    $reservation->getInventoryItemId(),
                    $reservation->getWarehouseId()
                );

                if (!$stockLevel) {
                    throw new Exception("Stock level mismatch for reservation {$reservation->getId()}");
                }

                // สั่ง StockLevel ย้ายถัง (Logic นี้ต้องอยู่ใน StockLevel Aggregate)
                $stockLevel->convertSoftToHard($reservation->getQuantity());

                // 4. Persist Changes
                $this->reservationRepo->save($reservation);
                $this->stockLevelRepo->save($stockLevel);
            }
        });
    }

    /**
     * Flow 1: Create Soft Reserve (Intent)
     */
    public function createSoftReservation(string $orderId, array $items, string $warehouseId): void
    {
        DB::transaction(function () use ($orderId, $items, $warehouseId) {
            foreach ($items as $item) {
                // Check Availability
                $stockLevel = $this->stockLevelRepo->findByItemAndWarehouse($item['product_id'], $warehouseId);

                if ($stockLevel->getAvailableQuantity() < $item['quantity']) {
                    throw new Exception("Insufficient stock for item {$item['product_id']}");
                }

                // Create Reservation Entity
                $reservation = StockReservation::createSoft(
                    $item['product_id'],
                    $warehouseId,
                    $orderId,
                    (float) $item['quantity']
                );

                // Update StockLevel (Reserve Quantity)
                $stockLevel->increaseSoftReserve($item['quantity']);

                $this->reservationRepo->save($reservation);
                $this->stockLevelRepo->save($stockLevel);
            }
        });
    }
}
