<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Maintenance\Domain\Models\ActivityType;
use TmrEcosystem\Shared\Domain\Models\Company;

class ActivityTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->command->error('No companies found. Skipping ActivityTypeSeeder.');
            return;
        }

        // (ข้อมูล Master Data อ้างอิงจากบทความ #3)
        $activities = [
            ['code' => 'INSP', 'name' => 'Inspection'],
            ['code' => 'LUB', 'name' => 'Lubrication'],
            ['code' => 'ADJ', 'name' => 'Adjustment'],
            ['code' => 'REPL', 'name' => 'Replacement'],
            ['code' => 'OVH', 'name' => 'Overhaul'],
            ['code' => 'CAL', 'name' => 'Calibration'],
            ['code' => 'CLN', 'name' => 'Cleaning'],
        ];

        foreach ($companies as $company) {
            foreach ($activities as $activity) {
                ActivityType::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $activity['code'],
                    ],
                    [
                        'name' => $activity['name'],
                    ]
                );
            }
        }

        $this->command->info('Activity Types seeded successfully.');
    }
}
