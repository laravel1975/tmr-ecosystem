<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_picking_slips', function (Blueprint $table) {
            // เพิ่มคอลัมน์ order_number เพื่อเก็บข้อมูล Snapshot
            $table->string('order_number')->nullable()->after('order_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_picking_slips', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });
    }
};
