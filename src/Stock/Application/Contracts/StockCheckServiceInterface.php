<?php

namespace TmrEcosystem\Stock\Application\Contracts;

interface StockCheckServiceInterface
{
    // ... (เมธอดเดิม checkAvailability, checkAvailabilityBatch) ...

    public function checkAvailability(string $partNumber, string $warehouseId): float;
    public function checkAvailabilityBatch(array $partNumbers, string $warehouseId): array;

    /**
     * ✅ [เพิ่มใหม่] ตรวจสอบยอดพร้อมขายโดยใช้ Item UUID (เร็วกว่าใช้ Part Number)
     */
    public function getAvailableQuantity(string $itemId, string $warehouseId): float;

    /**
     * ✅ ดึงข้อมูลสรุปสต็อก (On Hand, Reserved, Incoming)
     * @return array { on_hand: float, outgoing: float, incoming: float }
     */
    public function getStockSummary(string $itemUuid, string $companyId): array;
}
