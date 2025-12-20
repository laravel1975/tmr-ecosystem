<?php

namespace TmrEcosystem\Inventory\Domain\Aggregates;

use DateTimeImmutable;
use Exception;
use Illuminate\Support\Str;
use TmrEcosystem\Shared\Domain\Enums\ReservationState;

class StockReservation
{
    private string $id;
    private string $inventoryItemId;
    private string $warehouseId;
    private string $referenceId; // Sales Order ID
    private float $quantity;
    private ReservationState $state;
    private ?DateTimeImmutable $expiresAt;

    public function __construct(
        string $id,
        string $inventoryItemId,
        string $warehouseId,
        string $referenceId,
        float $quantity,
        ReservationState $state,
        ?DateTimeImmutable $expiresAt = null
    ) {
        $this->id = $id;
        $this->inventoryItemId = $inventoryItemId;
        $this->warehouseId = $warehouseId;
        $this->referenceId = $referenceId;
        $this->quantity = $quantity;
        $this->state = $state;
        $this->expiresAt = $expiresAt;
    }

    // --- Factory Method ---

    public static function createSoft(
        string $inventoryItemId,
        string $warehouseId,
        string $orderId,
        float $quantity,
        int $ttlMinutes = 60
    ): self {
        if ($quantity <= 0) {
            throw new Exception("Reservation quantity must be positive.");
        }

        return new self(
            (string) Str::uuid(),
            $inventoryItemId,
            $warehouseId,
            $orderId,
            $quantity,
            ReservationState::SOFT_RESERVED,
            new DateTimeImmutable("+{$ttlMinutes} minutes")
        );
    }

    // --- Domain Logic ---

    public function promoteToHard(): void
    {
        if ($this->state !== ReservationState::SOFT_RESERVED) {
            throw new Exception("Only SOFT reservations can be promoted to HARD. Current: {$this->state->value}");
        }

        if ($this->isExpired()) {
            throw new Exception("Reservation has expired. Cannot promote.");
        }

        $this->state = ReservationState::HARD_RESERVED;
        $this->expiresAt = null; // Hard Reserve ไม่มีวันหมดอายุ จนกว่าจะ Picking
    }

    public function markAsPicking(): void
    {
        if ($this->state !== ReservationState::HARD_RESERVED) {
            throw new Exception("Item must be HARD reserved before picking.");
        }
        $this->state = ReservationState::PICKING;
    }

    public function release(): void
    {
        if ($this->state === ReservationState::FULFILLED) {
             throw new Exception("Cannot release a fulfilled reservation.");
        }
        $this->state = ReservationState::RELEASED;
        $this->expiresAt = null;
    }

    public function isExpired(): bool
    {
        return $this->state === ReservationState::SOFT_RESERVED
            && $this->expiresAt
            && $this->expiresAt < new DateTimeImmutable();
    }

    // --- Getters ---
    public function getId(): string { return $this->id; }
    public function getInventoryItemId(): string { return $this->inventoryItemId; }
    public function getWarehouseId(): string { return $this->warehouseId; }
    public function getQuantity(): float { return $this->quantity; }
    public function getState(): ReservationState { return $this->state; }
    public function getReferenceId(): string { return $this->referenceId; }
    public function getExpiresAt(): ?DateTimeImmutable { return $this->expiresAt; }
}
