<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. เพิ่ม Column location_uuid (Nullable ไปก่อน)
        Schema::table('stock_levels', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_levels', 'location_uuid')) {
                $table->uuid('location_uuid')->nullable()->after('warehouse_uuid');
            }
        });

        // 2. Data Migration (ย้ายข้อมูลเก่าไปลง Location "GENERAL")
        // ... (Logic ส่วนนี้เหมือนเดิมครับ) ...
        $stockLevels = DB::table('stock_levels')->whereNull('location_uuid')->get();

        foreach ($stockLevels as $stock) {
            $generalLoc = DB::table('warehouse_storage_locations')
                ->where('warehouse_uuid', $stock->warehouse_uuid)
                ->where('code', 'GENERAL')
                ->first();

            if (!$generalLoc) {
                $generalLocUuid = Str::uuid()->toString();
                DB::table('warehouse_storage_locations')->insert([
                    'uuid' => $generalLocUuid,
                    'warehouse_uuid' => $stock->warehouse_uuid,
                    'code' => 'GENERAL',
                    'barcode' => 'GENERAL-' . substr($stock->warehouse_uuid, 0, 4),
                    'type' => 'BULK',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $generalLocUuid = $generalLoc->uuid;
            }

            DB::table('stock_levels')
                ->where('id', $stock->id)
                ->update(['location_uuid' => $generalLocUuid]);
        }

        // 3. ปรับ Structure (จุดที่แก้ Error)
        Schema::table('stock_levels', function (Blueprint $table) {
            // A. ทำให้ location_uuid เป็น Not Null และเพิ่ม FK
            $table->uuid('location_uuid')->nullable(false)->change();
            $table->foreign('location_uuid')
                  ->references('uuid')
                  ->on('warehouse_storage_locations')
                  ->cascadeOnDelete();

            // B. แก้ไข Constraint (ต้องปลด FK ก่อนลบ Index)

            // 1. ปลด FK ของ company_id (ชื่อ default คือ table_column_foreign)
            $table->dropForeign(['company_id']);

            // 2. ลบ Unique Index เก่า
            $table->dropUnique('stock_level_unique_item_warehouse');

            // 3. สร้าง Unique Index ใหม่ (Item + Location)
            // (Index นี้ขึ้นต้นด้วย company_id เหมือนเดิม จะใช้รองรับ FK ได้)
            $table->unique(
                ['company_id', 'item_uuid', 'location_uuid'],
                'stock_level_unique_item_location'
            );

            // 4. ผูก FK ของ company_id กลับคืนมา
            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            // ย้อนกลับ: ปลด FK company -> ลบ Unique ใหม่ -> คืน Unique เก่า -> คืน FK company
            $table->dropForeign(['company_id']);
            $table->dropUnique('stock_level_unique_item_location');

            $table->unique(['company_id', 'item_uuid', 'warehouse_uuid'], 'stock_level_unique_item_warehouse');

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            $table->dropForeign(['location_uuid']);
            $table->dropColumn('location_uuid');
        });
    }
};
