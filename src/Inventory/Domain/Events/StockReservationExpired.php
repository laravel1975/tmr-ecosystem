<?php

namespace TmrEcosystem\Inventory\Domain\Events;

class StockReservationExpired
{
    public function __construct(
        public string $reservationId,
        public string $orderId,
        public string $inventoryItemId,
        public float $quantity
    ) {}
}
