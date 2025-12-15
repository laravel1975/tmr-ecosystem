<?php

namespace TmrEcosystem\Sales\Application\DTOs;

readonly class CreateOrderDto
{
    /**
     * @param string $customerId
     * @param string $companyId
     * @param string $warehouseId
     * @param CreateOrderItemDto[] $items
     * @param string|null $note
     * @param string|null $paymentTerms
     * @param bool $confirmOrder
     */
    public function __construct(
        public string $customerId,
        public string $companyId,
        public string $warehouseId,
        public ?string $salespersonId,
        public array $items,
        public ?string $note = null,
        public ?string $paymentTerms = null,
        public bool $confirmOrder = false
    ) {}

    // ✅ ปรับ Factory method ให้รับ Context
    public static function fromRequest(array $data, string $companyId, string $warehouseId, ?string $salespersonId = null): self
    {
        $items = array_map(
            fn($item) => new CreateOrderItemDto($item['product_id'], (int)$item['quantity']),
            $data['items']
        );

        return new self(
            customerId: $data['customer_id'],
            companyId: $companyId,
            warehouseId: $warehouseId,
            salespersonId: $salespersonId,
            items: $items,
            note: $data['note'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null
        );
    }
}
