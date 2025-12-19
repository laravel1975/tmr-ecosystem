<?php

namespace TmrEcosystem\Sales\Application\DTOs;

class OrderItemSnapshotDto
{
    public function __construct(
        // ✅ [เพิ่ม] รับค่า ID ของรายการ (Line Item ID)
        public readonly string $id,
        public readonly string $productId,
        public readonly string $productName,
        public readonly int $quantity,
        public readonly float $unitPrice
    ) {}
}
