<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\Shared\Domain\Models\Company;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // --- 1. ค้นหา Company ---
        $company = Company::where('name', 'Modern Furniture Part., Ltd.')->first();
        if (!$company) {
            $this->command->error('Company "Modern Furniture Part., Ltd." not found. Please run CompanySeeder first.');
            return;
        }

        // สร้าง Permissions พร้อม guard_name
        $permissions = [
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'manage companies',
            'view audit log',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // สร้าง Roles พร้อม guard_name
        $superAdminRole     = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole          = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $managerRole        = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $userRole           = Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
        $officerRole        = Role::firstOrCreate(['name' => 'Officer', 'guard_name' => 'web']);
        $salespersonRole    = Role::firstOrCreate(['name' => 'Salesperson', 'guard_name' => 'web']);
        $guestRole          = Role::firstOrCreate(['name' => 'Guest', 'guard_name' => 'web']);

        // กำหนดสิทธิ์ให้ Role
        $adminRole->givePermissionTo([
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            'view employees',
            'create employees',
            'edit employees',
            'delete employees'
        ]);
        $managerRole->givePermissionTo(['view users']);

        // สร้าง Super Admin user (optional)
        $superAdminUser = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'bestcsv@gmail.com',
            'company_id' => null,
        ]);
        // Super Admin → มีทุกสิทธิ์
        $superAdminUser->assignRole($superAdminRole);

        // สร้าง Super Admin user (optional)
        $adminUser = User::factory()->create([
            'name' => 'Admin',
            'email' => 'it.tmrgroup@gmail.com',
            'company_id' => $company->id,
        ]);
        // Admin → มีทุกสิทธิ์
        $adminUser->assignRole($adminRole);
    }
}
