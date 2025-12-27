<?php

namespace TmrEcosystem\Stock\Application\DTOs;

class StockPickingSuggestionDto
{
    public function __construct(
        public ?string $locationId,
        public float $quantity,
        public ?string $batchId = null
    ) {}
}
