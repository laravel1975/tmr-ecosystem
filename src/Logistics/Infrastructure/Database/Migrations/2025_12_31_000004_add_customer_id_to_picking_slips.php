<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_picking_slips', function (Blueprint $table) {
            // เพิ่ม customer_id (Nullable ไว้ก่อนเผื่อข้อมูลเก่า แต่ Index ไว้เผื่อ search)
            $table->uuid('customer_id')->nullable()->after('order_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_picking_slips', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });
    }
};
