<?php

namespace TmrEcosystem\Sales\Application\Contracts;

use TmrEcosystem\Sales\Application\DTOs\CustomerDto;

interface CustomerLookupInterface
{
    public function findById(string $id): ?CustomerDto;
}
