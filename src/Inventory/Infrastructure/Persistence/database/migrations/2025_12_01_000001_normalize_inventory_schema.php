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

        // 3. เตรียมตาราง Items: เพิ่ม Columns ใหม่ (nullable ไปก่อนเพื่อรอ data migration)
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
                        'symbol' => strtolower(substr(trim($item->uom), 0, 3)), // Auto generate symbol
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

        // 5. Cleanup: ลบ Columns เก่าและบังคับ Not Null (ถ้าต้องการ)
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'uom']);
            // $table->uuid('category_id')->nullable(false)->change(); // Uncomment ถ้าต้องการบังคับ
            // $table->uuid('uom_id')->nullable(false)->change(); // Uncomment ถ้าต้องการบังคับ
        });
    }

    public function down(): void
    {
        // ย้อนกลับ: สร้าง column คืน และดึงข้อมูลกลับ (Simplified)
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->string('uom')->nullable();
        });

        // (ในทางปฏิบัติ การ down ข้อมูลที่ normalize แล้วอาจจะทำได้ยากหรือต้องเขียน script สวนทาง)
        // เพื่อความกระชับ จะทำการ Drop FK และ Tables

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['uom_id']);
            $table->dropColumn(['category_id', 'uom_id']);
        });

        Schema::dropIfExists('inventory_uoms');
        Schema::dropIfExists('inventory_categories');
    }
};
