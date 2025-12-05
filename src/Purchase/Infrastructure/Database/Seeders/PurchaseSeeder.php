<?php

namespace TmrEcosystem\Purchase\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Purchase\Domain\Models\Vendor;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Vendor::count() === 0) {
            Vendor::create([
                'code' => 'VEN-001',
                'name' => 'Acme Supplies Co., Ltd.',
                'tax_id' => '1234567890123',
                'address' => '123 Industrial Estate, Bangkok',
                'contact_person' => 'John Doe',
                'email' => 'sales@acme.com',
                'phone' => '02-999-9999',
                'credit_term_days' => 30
            ]);

             Vendor::create([
                'code' => 'VEN-002',
                'name' => 'Global Parts Inc.',
                'tax_id' => '9876543210987',
                'address' => '456 Tech Park, Rayong',
                'contact_person' => 'Jane Smith',
                'email' => 'contact@globalparts.com',
                'phone' => '038-888-8888',
                'credit_term_days' => 60
            ]);
        }
    }
}
