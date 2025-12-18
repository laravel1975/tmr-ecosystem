<?php

namespace TmrEcosystem\Stock\Application\Contracts;

interface StockPickingServiceInterface
{
    /**
     * คำนวณแผนการหยิบสินค้า (Picking Plan)
     * * @param string $warehouseId
     * @param array $items Array ของ ['product_id' => string, 'quantity' => float]
     * @return array
     */
    public function planPicking(string $warehouseId, array $items): array;
}
