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
            // เพิ่มคอลัมน์ product_name
            $table->string('product_name')->after('sales_order_item_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('logistics_picking_slip_items', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }
};
