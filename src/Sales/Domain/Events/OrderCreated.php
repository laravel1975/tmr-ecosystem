<?php

namespace TmrEcosystem\Sales\Domain\Events;

use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;

class OrderCreated
{
    public function __construct(
        public string $orderId,
        public OrderSnapshotDto $snapshot // Contains items, qty, warehouse info
    ) {}
}
