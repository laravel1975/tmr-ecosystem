<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Maintenance\Domain\Models\MaintenancePlan;
use TmrEcosystem\Maintenance\Domain\Models\MaintenanceType;
use TmrEcosystem\Maintenance\Domain\Models\Asset;
use TmrEcosystem\Shared\Domain\Models\Company;

class MaintenancePlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->command->error('No companies found. Skipping MaintenancePlanSeeder.');
            return;
        }

        foreach ($companies as $company) {

            // (1. ค้นหา ID ของ Type PM และ PDM)
            $pmType = MaintenanceType::where('company_id', $company->id)
                ->where('code', 'PM')->first();

            $pdmType = MaintenanceType::where('company_id', $company->id)
                ->where('code', 'PDM')->first();

            if (!$pmType || !$pdmType) {
                $this->command->warn("Skipping PM plans for {$company->name} (PM/PDM Types not found).");
                continue;
            }

            // (2. ค้นหา Asset 3 รายการแรกของบริษัทนี้มาสร้างแผน)
            $assets = Asset::where('company_id', $company->id)->take(3)->get();

            if ($assets->isEmpty()) {
                $this->command->warn("Skipping PM plans for {$company->name} (No Assets found).");
                continue;
            }

            // (3. สร้างแผน PM สำหรับโรงงานพลาสติก)

            // แผน 1: PM เครื่องฉีดพลาสติก (Injection Molder)
            MaintenancePlan::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'asset_id' => $assets[0]->id,
                    'title' => 'แผน PM: ตรวจสอบระบบไฮดรอลิกและหล่อลื่น ' . $assets[0]->name,
                ],
                [
                    'maintenance_type_id' => $pmType->id,
                    'status' => 'active',
                    'trigger_type' => 'TIME', // (ตามเวลา)
                    'interval_days' => 30, // (ทุก 30 วัน)
                    'next_due_date' => now()->addDays(30), // (ครั้งถัดไปในอีก 30 วัน)
                ]
            );

            // แผน 2: PM เครื่องบดพลาสติก (Grinder)
            if (isset($assets[1])) {
                MaintenancePlan::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'asset_id' => $assets[1]->id,
                        'title' => 'แผน PM: ตรวจสอบและทำความสะอาดใบมีด/ตะแกรง ' . $assets[1]->name,
                    ],
                    [
                        'maintenance_type_id' => $pmType->id,
                        'status' => 'active',
                        'trigger_type' => 'TIME',
                        'interval_days' => 90, // (ทุก 3 เดือน)
                        'next_due_date' => now()->addDays(90),
                    ]
                );
            }

            // แผน 3: PDM (Predictive) แม่พิมพ์ (Mold)
            if (isset($assets[2])) {
                MaintenancePlan::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'asset_id' => $assets[2]->id,
                        'title' => 'แผน PDM: ตรวจสอบการสั่นสะเทือน (Vibration) ' . $assets[2]->name,
                    ],
                    [
                        'maintenance_type_id' => $pdmType->id,
                        'status' => 'active',
                        'trigger_type' => 'TIME', // (ในอนาคตจะเปลี่ยนเป็น 'METER')
                        'interval_days' => 60, // (ทุก 2 เดือน)
                        'next_due_date' => now()->addDays(60),
                    ]
                );
            }
        }

        $this->command->info('Maintenance Plans (PM/PDM) seeded successfully.');
    }
}
