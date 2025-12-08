<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use TmrEcosystem\Approval\Infrastructure\Database\Seeders\SalesWorkflowSeeder;
use TmrEcosystem\Customers\Infrastructure\Database\Seeders\CustomerSeeder;
use TmrEcosystem\Purchase\Infrastructure\Database\Seeders\PurchaseSeeder;
use TmrEcosystem\Stock\Infrastructure\Persistence\Database\Seeders\StockSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            CompanySeeder::class,
            RolePermissionSeeder::class,
            DepartmentSeeder::class,
            AssetSeeder::class,
            ActivityTypeSeeder::class,
            FailureCodeSeeder::class,
            MaintenanceTypeSeeder::class,
            SparePartSeeder::class,
            MaintenancePlanSeeder::class,
            EmployeeProfileSeeder::class,
            CustomerSeeder::class,
            VehicleSeeder::class,
            StockSeeder::class,
            ProductSeeder::class,
            SalesWorkflowSeeder::class,
            PurchaseSeeder::class
        ]);
    }
}
