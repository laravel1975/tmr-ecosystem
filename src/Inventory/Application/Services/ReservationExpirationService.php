<?php

namespace TmrEcosystem\Inventory\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Inventory\Domain\Repositories\StockReservationRepositoryInterface;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Domain\Events\StockReservationExpired;
use Exception;

class ReservationExpirationService
{
    public function __construct(
        private StockReservationRepositoryInterface $reservationRepo,
        private StockLevelRepositoryInterface $stockLevelRepo
    ) {}

    public function processExpiredReservations(): int
    {
        $expiredReservations = $this->reservationRepo->findExpiredReservations();
        $count = 0;

        foreach ($expiredReservations as $reservation) {
            DB::beginTransaction();
            try {
                // 1. Load StockLevel
                $stockLevel = $this->stockLevelRepo->findByItemAndWarehouse(
                    $reservation->getInventoryItemId(),
                    $reservation->getWarehouseId()
                );

                if ($stockLevel) {
                    // 2. Release Stock (คืนของเข้ากองกลาง)
                    $stockLevel->releaseSoftReservation($reservation->getQuantity());
                    $this->stockLevelRepo->save($stockLevel);
                }

                // 3. Mark Reservation as Expired
                $reservation->markAsExpired();
                $this->reservationRepo->save($reservation);

                // 4. Dispatch Event (Notify Sales BC)
                event(new StockReservationExpired(
                    $reservation->getId(),
                    $reservation->getReferenceId(), // Order ID
                    $reservation->getInventoryItemId(),
                    $reservation->getQuantity()
                ));

                DB::commit();
                $count++;
                Log::info("Reservation expired: {$reservation->getId()} for Order {$reservation->getReferenceId()}");

            } catch (Exception $e) {
                DB::rollBack();
                Log::error("Failed to expire reservation {$reservation->getId()}: " . $e->getMessage());
            }
        }

        return $count;
    }
}
