<?php

namespace TmrEcosystem\Sales\Domain\Entities;

use TmrEcosystem\Shared\Domain\ValueObjects\Money;

class OrderItem
{
    public function __construct(
        public readonly string $productId,
        public readonly string $productName,
        public readonly float $unitPrice,
        public readonly int $quantity,
        // [FIX] เปลี่ยนจาก ?int เป็น ?string เพื่อรองรับ UUID
        public readonly ?string $id = null,
        public readonly int $qtyShipped = 0
    ) {}

    public function total(): float
    {
        return $this->unitPrice * $this->quantity;
    }

    public static function fromStorage(object $data): self
    {
        return new self(
            productId: $data->product_id,
            productName: $data->product_name,
            unitPrice: (float) $data->unit_price,
            quantity: (int) $data->quantity,
            // [FIX] ไม่ต้อง Cast เป็น int แล้ว เพราะรับ String ได้
            id: $data->id,
            qtyShipped: (int) ($data->qty_shipped ?? 0)
        );
    }
}
