<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_storage_locations', function (Blueprint $table) {
            // เพิ่มความจุ (null = ไม่จำกัด)
            $table->decimal('max_capacity', 10, 2)->nullable()->after('type')
                  ->comment('ความจุสูงสุด (หน่วยชิ้น)');

            // เพิ่มสถานะ Lock ไม่ให้เติมของ
            $table->boolean('is_full')->default(false)->after('max_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_storage_locations', function (Blueprint $table) {
            $table->dropColumn(['max_capacity', 'is_full']);
        });
    }
};
