<?php

namespace TmrEcosystem\Customers\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ลูกค้าเครดิตดี (VIP)
        Customer::create([
            'company_id' => 1, // ✅ สำคัญ: ต้องระบุบริษัท
            'code' => 'C001',
            'name' => 'บริษัท ตัวอย่าง จำกัด (VIP)',
            'tax_id' => '1234567890123',
            'address' => '123 ถ.สุขุมวิท กทม.',
            'credit_limit' => 1000000.00, // วงเงิน 1 ล้าน
            'outstanding_balance' => 0.00,
            'credit_term_days' => 30,     // เครดิต 30 วัน
            'is_credit_hold' => false,
        ]);

        // 2. ลูกค้าเงินสด (Cash Only)
        Customer::create([
            'company_id' => 1,
            'code' => 'C002',
            'name' => 'ร้านค้า ปลีกย่อย (เงินสด)',
            'tax_id' => '0987654321098',
            'address' => 'ตลาดไท ปทุมธานี',
            'credit_limit' => 0.00,       // 0 = ไม่จำกัด หรือต้องจ่ายสด (แล้วแต่ตกลง)
            'outstanding_balance' => 0.00,
            'credit_term_days' => 0,      // จ่ายทันที
            'is_credit_hold' => false,
        ]);

        // 3. ลูกค้าที่มีหนี้ค้างเยอะ (ใกล้เต็มวงเงิน) - เอาไว้เทสระบบแจ้งเตือน
        Customer::create([
            'company_id' => 1,
            'code' => 'C003',
            'name' => 'บริษัท หนี้เยอะ จำกัด',
            'tax_id' => '1111111111111',
            'address' => 'นิคมอุตสาหกรรมบางปู',
            'credit_limit' => 500000.00,
            'outstanding_balance' => 450000.00, // เหลือวงเงินแค่ 50,000
            'credit_term_days' => 60,
            'is_credit_hold' => false,
        ]);

        // 4. ลูกค้าติด Blacklist (Hold) - เอาไว้เทสว่าห้ามเปิดบิล
        Customer::create([
            'company_id' => 1,
            'code' => 'C004',
            'name' => 'ร้านค้า ค้างชำระ (Blacklist)',
            'address' => 'ไม่ทราบที่อยู่แน่ชัด',
            'credit_limit' => 100000.00,
            'outstanding_balance' => 120000.00, // เกินวงเงิน
            'credit_term_days' => 30,
            'is_credit_hold' => true, // ⛔ ติดสถานะระงับ
        ]);

        // สร้างลูกค้าทั่วไปเพิ่มอีกเล็กน้อย
        for ($i = 5; $i <= 8; $i++) {
            Customer::create([
                'company_id' => 1,
                'code' => 'C00' . $i,
                'name' => 'ลูกค้า ทดสอบรายที่ ' . $i,
                'address' => 'ที่อยู่ทดสอบ ' . $i,
                'credit_limit' => 200000.00,
                'outstanding_balance' => rand(0, 50000),
                'credit_term_days' => 30,
                'is_credit_hold' => false,
            ]);
        }
    }
}
