<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\HRM\Domain\Models\EmployeeProfile;
use TmrEcosystem\Shared\Domain\Models\Company;

class EmployeeProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- 1. ค้นหา Company ---
        $company = Company::where('name', 'Modern Furniture Part., Ltd.')->first();
        if (!$company) {
            $this->command->error('Company "Modern Furniture Part., Ltd." not found. Please run CompanySeeder first.');
            return;
        }

        // --- 2. ค้นหา User (Admin) ---
        $user = User::where('email', 'it.tmrgroup@gmail.com')->first();
        if (!$user) {
            $this->command->error('User "Admin" (it.tmrgroup@gmail.com) not found. Please run RolePermissionSeeder first.');
            return;
        }

        // --- 3. ตรวจสอบว่า User นี้มี Profile แล้วหรือยัง ---
        if ($user->profile) {
            $this->command->warn("User {$user->email} already has an Employee Profile. Skipping.");
            return;
        }

        // --- 4. สร้าง EmployeeProfile เพื่อเชื่อมโยง ---
        EmployeeProfile::create([
            'first_name' => 'Admin',
            'last_name' => 'TMR',
            'job_title' => 'System Administrator',
            'user_id' => $user->id,
            'company_id' => $company->id,
            'employee_id_no' => 'ADMIN-001',
            'join_date' => now(),

            'employment_type' => 'full_time',
            'employment_status' => 'confirmed',

            'resigned_date' => null,
        ]);

        $this->command->info("Employee Profile for {$user->email} created and linked to {$company->name}.");
    }
}
