<?php

namespace TmrEcosystem\Sales\Application\DTOs;

class CreateOrderItemDto
{
    public function __construct(
        public readonly string $productId,
        public readonly int $quantity,
        // ✅ [แก้ไข] เพิ่ม id เข้ามา และกำหนด default เป็น null (เพื่อไม่ให้กระทบ Create Order ปกติ)
        public readonly ?string $id = null
    ) {}
}
