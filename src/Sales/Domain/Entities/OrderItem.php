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
        public readonly ?int $id = null,
        public readonly int $qtyShipped = 0
    ) {}

    public function total(): float
    {
        return $this->unitPrice * $this->quantity;
    }

    // Factory Method สำหรับสร้างจาก DB (เพื่อความชัดเจน)
    public static function fromStorage(object $data): self
    {
        return new self(
            productId: $data->product_id,
            productName: $data->product_name,
            unitPrice: (float) $data->unit_price,
            quantity: (int) $data->quantity,
            id: $data->id,
            qtyShipped: (int) ($data->qty_shipped ?? 0)
        );
    }
}
