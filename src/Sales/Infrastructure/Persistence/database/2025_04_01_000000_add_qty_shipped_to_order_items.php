<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            // เพิ่มช่องเก็บจำนวนที่ส่งแล้ว (Default 0)
            $table->integer('qty_shipped')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('qty_shipped');
        });
    }
};
