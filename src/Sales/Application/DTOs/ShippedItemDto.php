<?php

namespace TmrEcosystem\Sales\Application\DTOs;

class ShippedItemDto
{
    public function __construct(
        public string $sales_order_item_id,
        public float $quantity_picked
    ) {}
}
