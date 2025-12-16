<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use TmrEcosystem\Shared\Domain\Models\Company;

return new class extends Migration
{
    public function up(): void
    {
        // 1. สร้างตาราง Master: Categories
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
        });

        // 2. สร้างตาราง Master: UoMs (Units of Measure)
        Schema::create('inventory_uoms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol')->nullable(); // e.g. pcs, box, kg
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
        });

        // 3. เตรียมตาราง Items: เพิ่ม Columns ใหม่
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignUuid('category_id')->nullable()->after('category')->constrained('inventory_categories');
            $table->foreignUuid('uom_id')->nullable()->after('uom')->constrained('inventory_uoms');
        });

        // 4. Data Migration Script: ย้ายข้อมูลเก่า -> ใหม่
        $items = DB::table('inventory_items')->get();

        foreach ($items as $item) {
            // A. Handle Category
            if (!empty($item->category)) {
                $category = DB::table('inventory_categories')
                    ->where('company_id', $item->company_id)
                    ->where('name', trim($item->category))
                    ->first();

                if (!$category) {
                    $catId = Str::uuid()->toString();
                    DB::table('inventory_categories')->insert([
                        'id' => $catId,
                        'company_id' => $item->company_id,
                        'name' => trim($item->category),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $catId = $category->id;
                }

                DB::table('inventory_items')
                    ->where('uuid', $item->uuid)
                    ->update(['category_id' => $catId]);
            }

            // B. Handle UoM
            if (!empty($item->uom)) {
                $uom = DB::table('inventory_uoms')
                    ->where('company_id', $item->company_id)
                    ->where('name', trim($item->uom))
                    ->first();

                if (!$uom) {
                    $uomId = Str::uuid()->toString();
                    DB::table('inventory_uoms')->insert([
                        'id' => $uomId,
                        'company_id' => $item->company_id,
                        'name' => trim($item->uom),
                        'symbol' => strtolower(substr(trim($item->uom), 0, 3)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $uomId = $uom->id;
                }

                DB::table('inventory_items')
                    ->where('uuid', $item->uuid)
                    ->update(['uom_id' => $uomId]);
            }
        }

        // 5. Cleanup: ลบ Columns เก่า
        Schema::table('inventory_items', function (Blueprint $table) {
            // [FIX] ต้องลบ Index ก่อนลบ Column เสมอ (โดยเฉพาะ SQLite)
            // ชื่อ index ปกติจะเป็น table_column_index
            $table->dropIndex(['category']);

            $table->dropColumn(['category', 'uom']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('category')->nullable()->index(); // Add index back
            $table->string('uom')->nullable();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['uom_id']);
            $table->dropColumn(['category_id', 'uom_id']);
        });

        Schema::dropIfExists('inventory_uoms');
        Schema::dropIfExists('inventory_categories');
    }
};
