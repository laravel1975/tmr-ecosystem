<?php

namespace TmrEcosystem\Sales\Application\DTOs;

class CustomerDto
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $taxId,
        public ?string $email,
        public ?string $phone,
        public ?string $address, // หรือแยกเป็น billing/shipping address ตามโครงสร้างจริง
        public ?string $shippingAddress,
        public ?string $paymentTerms,
        public ?string $defaultSalespersonId,
    ) {}

    /**
     * Helper สำหรับแปลงเป็น Array เพื่อเก็บลง Snapshot JSON
     */
    public function toSnapshotArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tax_id' => $this->taxId,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'shipping_address' => $this->shippingAddress,
            'payment_terms' => $this->paymentTerms,
        ];
    }
}
