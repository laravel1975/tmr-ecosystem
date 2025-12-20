<?php

namespace TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use TmrEcosystem\Inventory\Domain\Aggregates\StockReservation;
use TmrEcosystem\Inventory\Domain\Repositories\StockReservationRepositoryInterface;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\StockReservationModel;
use TmrEcosystem\Shared\Domain\Enums\ReservationState;

class EloquentStockReservationRepository implements StockReservationRepositoryInterface
{
    public function save(StockReservation $reservation): void
    {
        StockReservationModel::updateOrCreate(
            ['id' => $reservation->getId()],
            [
                'inventory_item_id' => $reservation->getInventoryItemId(),
                'warehouse_id' => $reservation->getWarehouseId(),
                'reference_id' => $reservation->getReferenceId(), // วิธีเข้าถึงต้องเพิ่ม Getter ใน Aggregate
                'quantity' => $reservation->getQuantity(),
                'state' => $reservation->getState()->value,
                'expires_at' => $reservation->getExpiresAt(), // เพิ่ม Getter ใน Aggregate
            ]
        );
    }

    public function findById(string $id): ?StockReservation
    {
        $model = StockReservationModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function findByReferenceId(string $referenceId): Collection
    {
        return StockReservationModel::where('reference_id', $referenceId)
            ->get()
            ->map(fn($model) => $this->toDomain($model));
    }

    public function findExpiredReservations(): Collection
    {
        return StockReservationModel::where('state', ReservationState::SOFT_RESERVED->value)
            ->where('expires_at', '<', now())
            ->get()
            ->map(fn($model) => $this->toDomain($model));
    }

    private function toDomain(StockReservationModel $model): StockReservation
    {
        // ต้องมั่นใจว่า Constructor ของ Aggregate รับค่าเหล่านี้ครบ
        return new StockReservation(
            $model->id,
            $model->inventory_item_id,
            $model->warehouse_id,
            $model->reference_id,
            $model->quantity,
            ReservationState::from($model->state),
            $model->expires_at ? DateTimeImmutable::createFromMutable($model->expires_at) : null
        );
    }
}
