<?php

namespace TmrEcosystem\Inventory\Domain\Repositories;

use TmrEcosystem\Inventory\Domain\Aggregates\StockReservation;
use Illuminate\Support\Collection;

interface StockReservationRepositoryInterface
{
    public function save(StockReservation $reservation): void;

    public function findById(string $id): ?StockReservation;

    /**
     * @return Collection<int, StockReservation>
     */
    public function findByReferenceId(string $referenceId): Collection;

    /**
     * สำหรับ Cron Job ค้นหา Reservation ที่หมดอายุ
     * @return Collection<int, StockReservation>
     */
    public function findExpiredReservations(): Collection;
}
