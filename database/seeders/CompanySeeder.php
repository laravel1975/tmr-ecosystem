<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str; // <-- 2. Import Str (สำหรับ Slug)
use TmrEcosystem\Shared\Domain\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ล้างข้อมูลเก่า (ถ้าต้องการ)
        // Company::truncate();

        $companies = [
            [
                'name' => 'Modern Furniture Part., Ltd.',
                'slug' => Str::slug('Modern Furniture Part Ltd'),
                'is_active' => true,
            ],
            [
                'name' => 'Royce Universal Co., Ltd.',
                'slug' => Str::slug('Royce Universal Co Ltd'),
                'is_active' => true,
            ],
            [
                'name' => 'Thai Creative Lighting Co., Ltd.',
                'slug' => Str::slug('Thai Creative Lighting Co Ltd'),
                'is_active' => true,
            ],
        ];

        // 3. สร้างข้อมูลพร้อมกัน
        foreach ($companies as $company) {
            Company::create($company);
        }
    }
}
