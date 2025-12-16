<?php

namespace TmrEcosystem\Sales\Application\DTOs;

class OrderSnapshotDto
{
    /**
     * @param OrderItemSnapshotDto[] $items
     */
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public string $customerId,
        public string $companyId,
        public string $warehouseId,
        public array $items,
        public ?string $note = null
    ) {}
}
