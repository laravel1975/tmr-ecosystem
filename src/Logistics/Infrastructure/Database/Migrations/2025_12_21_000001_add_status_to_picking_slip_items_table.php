<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('logistics_picking_slip_items', function (Blueprint $table) {
            // เพิ่มคอลัมน์ status ต่อจาก quantity_picked
            // กำหนด default เป็น 'pending' เพื่อความปลอดภัยสำหรับข้อมูลเก่า (ถ้ามี)
            $table->string('status')->default('pending')->after('quantity_picked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logistics_picking_slip_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
