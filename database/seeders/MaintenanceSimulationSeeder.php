<?php
// เรียกใช้งาน :: php artisan db:seed --class=MaintenanceSimulationSeeder

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use TmrEcosystem\Maintenance\Domain\Models\WorkOrder;
use TmrEcosystem\Maintenance\Domain\Models\MaintenanceAssignment;
use TmrEcosystem\Maintenance\Domain\Models\MaintenanceTechnician;
use TmrEcosystem\Maintenance\Domain\Models\WorkOrderSparePart;
use TmrEcosystem\Maintenance\Domain\Models\Asset;
use TmrEcosystem\Maintenance\Domain\Models\MaintenanceType;
use TmrEcosystem\Maintenance\Domain\Models\FailureCode;
use TmrEcosystem\Maintenance\Domain\Models\ActivityType;
use TmrEcosystem\Maintenance\Domain\Models\SparePart;
use TmrEcosystem\Shared\Domain\Models\Company;

class MaintenanceSimulationSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $this->command->info("Simulating data for: {$company->name}");
            $this->simulateCompanyData($company);
        }
    }

    private function simulateCompanyData($company)
    {
        // 1. เตรียมข้อมูล Master Data
        $assets = Asset::where('company_id', $company->id)->get();
        $types = MaintenanceType::where('company_id', $company->id)->get();
        $failureCodes = FailureCode::where('company_id', $company->id)->whereNotNull('parent_id')->get(); // เอาเฉพาะลูก
        $activityTypes = ActivityType::where('company_id', $company->id)->get();
        $spareParts = SparePart::where('company_id', $company->id)->get();
        $technicians = MaintenanceTechnician::where('company_id', $company->id)->get();

        if ($assets->isEmpty() || $technicians->isEmpty()) {
            $this->command->warn("Skipping {$company->name} (Missing assets or technicians)");
            return;
        }

        // (อัปเดตค่าแรงช่าง ถ้าเป็น null)
        foreach ($technicians as $tech) {
            if (!$tech->hourly_rate) {
                $tech->update(['hourly_rate' => rand(150, 500)]); // ค่าแรง 150-500 บาท
            }
        }

        // 2. สร้าง Work Order ย้อนหลัง 90 วัน
        // (สร้างประมาณ 50-100 ใบ)
        $totalWos = rand(50, 100);

        for ($i = 0; $i < $totalWos; $i++) {

            // (สุ่มวันที่ย้อนหลัง)
            $createdAt = Carbon::now()->subDays(rand(0, 90))->subHours(rand(0, 24));

            // (สุ่มสถานะ: ส่วนใหญ่ Completed)
            $status = (rand(1, 10) > 2) ? 'closed' : 'open';

            $wo = WorkOrder::create([
                'company_id' => $company->id,
                'work_order_code' => 'SIM-' . $createdAt->format('Ymd') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'asset_id' => $assets->random()->id,
                'maintenance_type_id' => $types->random()->id,
                'status' => $status,
                'priority' => ['P1', 'P2', 'P3', 'P4'][rand(0, 3)],
                'work_nature' => 'Internal',
                'description' => 'Simulation Task #' . $i,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours(rand(2, 48)),
            ]);

            // 3. ถ้าสถานะ Closed -> ใส่ข้อมูล Analysis (Cost/Efficiency/RCA)
            if ($status === 'closed') {

                // (RCA & Efficiency)
                $wo->update([
                    'failure_code_id' => $failureCodes->isNotEmpty() ? $failureCodes->random()->id : null,
                    'activity_type_id' => $activityTypes->isNotEmpty() ? $activityTypes->random()->id : null,
                    'downtime_hours' => (rand(1, 10) > 7) ? rand(1, 24) : 0, // 30% มี Downtime
                ]);

                // (Labor Cost - Assign ช่าง 1-2 คน)
                $assignedTechs = $technicians->random(rand(1, min(2, $technicians->count())));

                foreach ($assignedTechs as $tech) {
                    $hours = rand(1, 8); // ทำงาน 1-8 ชม.
                    $cost = $hours * $tech->hourly_rate;

                    MaintenanceAssignment::create([
                        'work_order_id' => $wo->id,
                        'assignable_type' => MaintenanceTechnician::class,
                        'assignable_id' => $tech->employee_profile_id,
                        'actual_labor_hours' => $hours,
                        'recorded_hourly_rate' => $tech->hourly_rate,
                        'labor_cost' => $cost,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                // (Spare Part Cost - สุ่มใช้อะไหล่)
                if ($spareParts->isNotEmpty() && rand(1, 10) > 5) { // 50% ใช้อะไหล่
                    $part = $spareParts->random();
                    $qty = rand(1, 5);

                    WorkOrderSparePart::create([
                        'work_order_id' => $wo->id,
                        'spare_part_id' => $part->id,
                        'quantity_used' => $qty,
                        'unit_cost_at_time' => $part->unit_cost ?? rand(100, 1000),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
        }

        $this->command->info("Generated {$totalWos} WOs for {$company->name}");
    }
}
