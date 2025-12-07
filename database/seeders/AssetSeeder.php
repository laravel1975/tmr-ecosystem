<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Maintenance\Domain\Models\Asset; // (2. Import Asset Model)
use Illuminate\Database\Eloquent\Factories\Sequence; // (3. Import Sequence สำหรับสร้าง Code)
use TmrEcosystem\Shared\Domain\Models\Company;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // (ล้างข้อมูลเก่า ถ้าต้องการ)
        // Asset::truncate();

        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->error('No companies found. Please run CompanySeeder first.');
            return;
        }

        $this->command->info('Seeding 50 assets for each company...');

        foreach ($companies as $company) {

            Asset::factory()
                ->count(50) // (4. สร้าง 50 รายการ)
                ->state(new Sequence(
                    // (5. [สำคัญ] Logic นี้จะสร้าง Code ที่ไม่ซ้ำกันในบริษัท)
                    // (ผลลัพธ์: MODERN-FURNITURE-PART-LTD-0001, ...-0002, ...)
                    fn (Sequence $sequence) => [
                        'asset_code' => strtoupper($company->slug) . '-' . str_pad($sequence->index + 1, 4, '0', STR_PAD_LEFT)
                    ]
                ))
                ->create([
                    'company_id' => $company->id // (6. กำหนด company_id)
                ]);

            $this->command->info("Created 50 assets for {$company->name}");
        }
    }
}
