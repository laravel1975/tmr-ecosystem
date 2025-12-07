<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Maintenance\Domain\Models\MaintenanceType; // (1. Import Model)
use TmrEcosystem\Shared\Domain\Models\Company;

class MaintenanceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->command->error('No companies found. Skipping MaintenanceTypeSeeder.');
            return;
        }

        // (อ้างอิงจากบทความ #1 - มาตรฐานโรงงานอุตสาหกรรม)
        $types = [
            [
                'code' => 'CM',
                'name' => 'Corrective Maintenance',
                'description' => 'งานซ่อมเชิงแก้ไข (ซ่อมเมื่อเสีย)'
            ],
            [
                'code' => 'EM',
                'name' => 'Emergency Maintenance',
                'description' => 'งานซ่อมฉุกเฉิน (เครื่องจักรหยุดผลิต)'
            ],
            [
                'code' => 'PM',
                'name' => 'Preventive Maintenance',
                'description' => 'งานซ่อมบำรุงเชิงป้องกัน (ตามรอบเวลา)'
            ],
            [
                'code' => 'PDM',
                'name' => 'Predictive Maintenance',
                'description' => 'งานซ่อมบำรุงเชิงคาดการณ์ (ตามสภาพ)'
            ],
            [
                'code' => 'IM',
                'name' => 'Improvement Maintenance',
                'description' => 'งานปรับปรุงเครื่องจักร'
            ],
        ];

        // (สร้างข้อมูลนี้ให้ครบทุกบริษัท)
        foreach ($companies as $company) {
            foreach ($types as $type) {
                MaintenanceType::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $type['code'],
                    ],
                    [
                        'name' => $type['name'],
                        'description' => $type['description'],
                    ]
                );
            }
        }

        $this->command->info('Maintenance Types (CM, PM, PDM) seeded successfully.');
    }
}
