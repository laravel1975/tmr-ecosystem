<?php

namespace TmrEcosystem\Sales\Application\Contracts;

use TmrEcosystem\Sales\Application\DTOs\ShippedItemDto;

interface ShippedItemProviderInterface
{
    /**
     * ดึงรายการสินค้าที่ถูกหยิบจริงตาม Picking Slip ID
     * @return ShippedItemDto[]
     */
    public function getByPickingSlipId(string $pickingSlipId): array;
}
