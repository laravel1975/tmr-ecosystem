<?php
// เรียกใช้งาน :: php artisan db:seed --class="Database\Seeders\ProductSeeder"

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB; // ✅ เพิ่ม DB Facade

// Models
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\InventoryCategory;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\InventoryUom;
use TmrEcosystem\Shared\Domain\Models\Company;
use TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockLevelModel;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // 1. หา Company หลัก
        $company = Company::first() ?? Company::factory()->create();

        // 2. หา Warehouse หลัก
        $warehouse = WarehouseModel::where('company_id', $company->id)->first();

        if (!$warehouse) {
             $warehouse = WarehouseModel::create([
                 'uuid' => Str::uuid(),
                 'company_id' => $company->id,
                 'name' => 'Main Warehouse',
                 'code' => 'WH-MAIN',
                 'is_active' => true,
                 'description' => 'Default warehouse created by seeder'
             ]);
        }

        // 2.5 ✅ [เพิ่มใหม่] หาหรือสร้าง Location 'GENERAL' (หัวใจสำคัญของการแก้ Error นี้)
        $generalLocId = DB::table('warehouse_storage_locations')
            ->where('warehouse_uuid', $warehouse->uuid)
            ->where('code', 'GENERAL')
            ->value('uuid');

        if (!$generalLocId) {
            $generalLocId = Str::uuid()->toString();
            DB::table('warehouse_storage_locations')->insert([
                'uuid' => $generalLocId,
                'warehouse_uuid' => $warehouse->uuid,
                'code' => 'GENERAL',
                'barcode' => 'GENERAL-' . substr($warehouse->uuid, 0, 4),
                'type' => 'BULK',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. เตรียม Master Data (Categories & UoMs)
        $categoryNames = ['Electronics', 'Furniture', 'Consumables', 'Services', 'Raw Material'];
        $uomNames = ['Units', 'kg', 'm', 'Liter', 'Box', 'Set'];

        $categoryIds = [];
        foreach ($categoryNames as $name) {
            $cat = InventoryCategory::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['id' => Str::uuid(), 'code' => strtoupper(substr($name, 0, 3))]
            );
            $categoryIds[] = $cat->id;
        }

        $uomIds = [];
        foreach ($uomNames as $name) {
            $uom = InventoryUom::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['id' => Str::uuid(), 'symbol' => strtolower(substr($name, 0, 3))]
            );
            $uomIds[] = $uom->id;
        }

        // 4. Loop สร้าง 300 รายการ
        for ($i = 0; $i < 300; $i++) {

            $type = $faker->randomElement(['product', 'consu', 'service']);
            $productName = ucwords($faker->words(3, true));

            // A. สร้าง Item (Inventory BC)
            $item = ItemModel::create([
                'uuid' => Str::uuid(),
                'company_id' => $company->id,
                'name' => $productName,
                'part_number' => strtoupper($faker->unique()->bothify('??-####-???')),
                'barcode' => $faker->ean13,
                'description' => $faker->sentence,
                'category_id' => $faker->randomElement($categoryIds),
                'uom_id' => $faker->randomElement($uomIds),
                'average_cost' => $faker->randomFloat(2, 10, 5000),
                'is_active' => $faker->boolean(90),
                'type' => $type,
                'can_purchase' => $faker->boolean(80),
                'can_sell' => $faker->boolean(80),
            ]);

            // B. สร้าง Stock Level (Stock BC)
            // ✅ ต้องเช็คว่าไม่ใช่ Service และมี Warehouse และมี Location
            if ($type !== 'service' && $warehouse && $generalLocId) {
                $qty = $faker->numberBetween(0, 100);

                if ($qty > 0) {
                    // ใช้ updateOrInsert เพื่อป้องกัน Duplicate Key Error (เผื่อรัน Seeder ซ้ำ)
                    StockLevelModel::updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'item_uuid' => $item->uuid,
                            'location_uuid' => $generalLocId, // ✅ ใส่ Location UUID ที่เตรียมไว้
                        ],
                        [
                            'uuid' => Str::uuid(),
                            'warehouse_uuid' => $warehouse->uuid,
                            'quantity_on_hand' => $qty,
                            'quantity_reserved' => 0,
                            'quantity_soft_reserved' => 0,
                        ]
                    );
                }
            }
        }

        $this->command->info('Seeded 300 Products with Location-Based Stock successfully!');
    }
}
