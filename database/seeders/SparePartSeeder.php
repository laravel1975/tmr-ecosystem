<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Maintenance\Domain\Models\SparePart;
use TmrEcosystem\Shared\Domain\Models\Company;

class SparePartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. ค้นหา Company
        $company = Company::where('name', 'Modern Furniture Part., Ltd.')->first();

        if (!$company) {
            $this->command->error('Company "Modern Furniture Part., Ltd." not found. Skipping SparePartSeeder.');
            return;
        }

        // 2. รายการอะไหล่สำหรับโรงงานฉีดพลาสติก (เฟอร์นิเจอร์)
        $spareParts = [
            [
                'part_number' => 'HTR-BND-50MM',
                'name' => 'Heater Band (ฮีตเตอร์รัดท่อ) 50mm',
                'location' => 'A-01',
                'stock_quantity' => 20,
                'reorder_level' => 10, // (ของสำคัญ สั่งเตือนที่ 10 ชิ้น)
                'unit_cost' => 1500.00
            ],
            [
                'part_number' => 'NZL-GEN-01',
                'name' => 'Nozzle Tip (หัวฉีดพลาสติก)',
                'location' => 'A-02',
                'stock_quantity' => 15,
                'reorder_level' => 5, // (สั่งเตือนที่ 5 ชิ้น)
                'unit_cost' => 3500.00
            ],
            [
                'part_number' => 'TC-TYPE-J',
                'name' => 'Thermocouple Type J (เซ็นเซอร์อุณหภูมิ)',
                'location' => 'B-01',
                'stock_quantity' => 30,
                'reorder_level' => 10,
                'unit_cost' => 800.00
            ],
            [
                'part_number' => 'OIL-HYD-ISO46',
                'name' => 'Hydraulic Oil (น้ำมันไฮดรอลิก) ISO 46',
                'location' => 'C-01',
                'stock_quantity' => 5, // (หน่วยเป็นถัง)
                'reorder_level' => 2, // (เหลือน้อยกว่า 2 ถังให้เตือน)
                'unit_cost' => 5500.00
            ],
            [
                'part_number' => 'FIL-HYD-001',
                'name' => 'Hydraulic Filter (ไส้กรองไฮดรอลิก)',
                'location' => 'A-03',
                'stock_quantity' => 50,
                'reorder_level' => 20, // (ใช้บ่อย)
                'unit_cost' => 1200.00
            ],
            [
                'part_number' => 'SCRW-M6-20',
                'name' => 'สกรู M6x20 (สำหรับประกอบชิ้นงาน)',
                'location' => 'D-10',
                'stock_quantity' => 5000,
                'reorder_level' => 1000, // (ใช้เยอะมาก)
                'unit_cost' => 0.50
            ],
        ];

        // 3. วนลูปสร้างข้อมูล
        foreach ($spareParts as $part) {
            SparePart::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'part_number' => $part['part_number'], // (ใช้ part_number กันซ้ำ)
                ],
                [
                    'name' => $part['name'],
                    'location' => $part['location'],
                    'stock_quantity' => $part['stock_quantity'],
                    'reorder_level' => $part['reorder_level'], // (กำหนดค่าขั้นต่ำ)
                    'unit_cost' => $part['unit_cost']
                ]
            );
        }

        $this->command->info('Spare parts for Modern Furniture Part., Ltd. seeded successfully.');
    }
}
