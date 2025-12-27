<?php

namespace TmrEcosystem\Customers\Infrastructure\Services;

use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\Sales\Application\Contracts\CustomerLookupInterface;
use TmrEcosystem\Sales\Application\DTOs\CustomerDto;

class SalesCustomerLookupService implements CustomerLookupInterface
{
    public function findById(string $id): ?CustomerDto
    {
        // Query Eloquent Model ของ Customer
        $customer = Customer::find($id);

        if (!$customer) {
            return null;
        }

        // Map Eloquent Model -> Sales DTO
        return new CustomerDto(
            id: (string) $customer->id,
            name: $customer->name,
            taxId: $customer->tax_id ?? null,
            email: $customer->email,
            phone: $customer->phone,
            address: $customer->address,
            // สมมติว่าใน DB Customers เก็บ shipping_address แยก หรือใช้ address เดียวกัน
            shippingAddress: $customer->shipping_address ?? $customer->address,
            paymentTerms: $customer->payment_terms ?? 'COD', // ค่า Default
            defaultSalespersonId: $customer->default_salesperson_id
        );
    }
}
