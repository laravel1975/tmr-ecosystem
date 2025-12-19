<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_picking_slip_items', function (Blueprint $table) {
            // ปรับคอลัมน์ให้เป็น String 36 ตัวอักษรเพื่อรองรับ UUID
            // ต้องลง package doctrine/dbal ก่อนหากใช้ Laravel เวอร์ชั่นเก่ากว่า 10
            $table->string('sales_order_item_id', 36)->change();
        });
    }

    public function down(): void
    {
        // กรณี Rollback (สมมติว่าของเดิมเป็น 32 หรือค่าอื่น คุณอาจต้องปรับตามจริง)
        Schema::table('logistics_picking_slip_items', function (Blueprint $table) {
            $table->string('sales_order_item_id', 32)->change();
        });
    }
};
