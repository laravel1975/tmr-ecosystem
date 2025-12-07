<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Maintenance\Domain\Models\FailureCode;
use TmrEcosystem\Shared\Domain\Models\Company;

class FailureCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->command->error('No companies found. Skipping FailureCodeSeeder.');
            return;
        }

        // (ข้อมูล Master Data อ้างอิงจากบทความ #2)
        $failureHierarchy = [
            'MECH' => ['name' => 'Mechanical', 'children' => [
                ['code' => 'MECH-BEAR', 'name' => 'Bearing Failure'],
                ['code' => 'MECH-GEAR', 'name' => 'Gear Damaged'],
                ['code' => 'MECH-SEAL', 'name' => 'Seal Leak'],
            ]],
            'ELEC' => ['name' => 'Electrical', 'children' => [
                ['code' => 'ELEC-MTR', 'name' => 'Motor Failure'],
                ['code' => 'ELEC-CTRL', 'name' => 'Control/Sensor Error'],
                ['code' => 'ELEC-WIR', 'name' => 'Wiring/Connection Loose'],
            ]],
            'HUMAN' => ['name' => 'Human Error', 'children' => [
                ['code' => 'HUMAN-OP', 'name' => 'Incorrect Operation'],
                ['code' => 'HUMAN-SETUP', 'name' => 'Incorrect Setup'],
            ]],
            'PROC' => ['name' => 'Process Related', 'children' => [
                ['code' => 'PROC-OVRLD', 'name' => 'Overload'],
            ]],
            'PM' => ['name' => 'Preventive Maintenance', 'children' => [
                ['code' => 'PM-Auto', 'name' => 'Preventive Maintenance']
            ]],
        ];

        foreach ($companies as $company) {
            foreach ($failureHierarchy as $parentCode => $data) {
                // 1. สร้าง Parent
                $parent = FailureCode::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $parentCode,
                    ],
                    [
                        'name' => $data['name'],
                        'parent_id' => null,
                    ]
                );

                // 2. สร้าง Children
                foreach ($data['children'] as $child) {
                    FailureCode::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'code' => $child['code'],
                        ],
                        [
                            'name' => $child['name'],
                            'parent_id' => $parent->id,
                        ]
                    );
                }
            }
        }

        $this->command->info('Failure Codes seeded successfully.');
    }
}
