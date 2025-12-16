<?php

namespace TmrEcosystem\Sales\Application\DTOs;

class OrderItemSnapshotDto
{
    public function __construct(
        public string $productId,
        public string $productName,
        public int $quantity,
        public float $unitPrice
    ) {}
}
