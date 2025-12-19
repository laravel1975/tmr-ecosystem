<?php

namespace TmrEcosystem\Sales\Application\DTOs;

use Illuminate\Http\Request;

class UpdateOrderDto
{
    public function __construct(
        public readonly string $customerId,
        public readonly array $items,
        public readonly ?string $note,
        public readonly ?string $paymentTerms,
        public readonly bool $confirmOrder = false // ✅ เพิ่มตัวแปรนี้
    ) {}

    public static function fromRequest(Request $request): self
    {
        // แปลงรายการสินค้า
        $items = array_map(fn($item) => new CreateOrderItemDto(
            productId: $item['product_id'],
            quantity: (int) $item['quantity'],
            id: $item['id'] ?? null // รองรับกรณีแก้ไขรายการเดิม
        ), $request->input('items', []));

        return new self(
            customerId: $request->input('customer_id'),
            items: $items,
            note: $request->input('note'),
            paymentTerms: $request->input('payment_terms'),
            // ✅ Logic สำคัญ: ถ้า action เป็น 'confirm' ให้ตั้งค่าเป็น true
            confirmOrder: $request->input('action') === 'confirm'
        );
    }
}
