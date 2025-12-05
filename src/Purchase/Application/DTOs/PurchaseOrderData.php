<?php

namespace TmrEcosystem\Purchase\Application\DTOs;

class PurchaseOrderData
{
    public function __construct(
        public int $vendor_id,
        public string $order_date,
        public ?string $expected_delivery_date,
        public ?string $notes,
        public array $items // Array of ['item_id' => int, 'quantity' => float, 'unit_price' => float]
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            vendor_id: $request->validated('vendor_id'),
            order_date: $request->validated('order_date'),
            expected_delivery_date: $request->validated('expected_delivery_date'),
            notes: $request->validated('notes'),
            items: $request->validated('items')
        );
    }
}
