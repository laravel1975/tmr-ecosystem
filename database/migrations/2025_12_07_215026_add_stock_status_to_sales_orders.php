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
        Schema::table('sales_orders', function (Blueprint $table) {
            // เพิ่ม enum หรือ string สำหรับสถานะสต็อกแยกต่างหาก
            $table->string('stock_status')->default('pending')->after('status')->index();
            // pending, reserved, backorder, fulfilled
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            //
        });
    }
};
