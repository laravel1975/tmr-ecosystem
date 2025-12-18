<?php

namespace TmrEcosystem\Sales\Domain\Entities;

use Exception;

class OrderItem
{
    public function __construct(
        public readonly string $productId,
        public readonly string $productName,
        public readonly float $unitPrice,
        public readonly int $quantity,
        public readonly ?string $id = null,
        public int $qtyShipped = 0
    ) {}

    public function total(): float
    {
        return $this->unitPrice * $this->quantity;
    }

    /**
     * อัปเดตจำนวนที่จัดส่งแล้วสำหรับสินค้ารายการนี้
     */
    public function updateShippedQty(int $shippedQty): void
    {
        if ($shippedQty < 0) {
            throw new Exception("Shipped quantity cannot be negative.");
        }

        if ($shippedQty > $this->quantity) {
            throw new Exception("Shipped quantity ({$shippedQty}) cannot exceed ordered quantity ({$this->quantity}).");
        }

        $this->qtyShipped = $shippedQty;
    }

    /**
     * ตรวจสอบว่ารายการนี้ส่งครบแล้วหรือยัง
     */
    public function isFullyShipped(): bool
    {
        return $this->qtyShipped >= $this->quantity;
    }

    public static function fromStorage(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            productName: $data['product_name'],
            unitPrice: (float) $data['unit_price'],
            quantity: (int) $data['quantity'],
            id: $data['id'] ?? null,
            qtyShipped: (int) ($data['qty_shipped'] ?? 0)
        );
    }

    public function toStorage(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'qty_shipped' => $this->qtyShipped,
        ];
    }
}
