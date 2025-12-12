<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ แก้ไข: เปลี่ยนชื่อตารางเป็น logistics_
        Schema::create('logistics_picking_slip_items', function (Blueprint $table) {
            $table->id();

            // ✅ แก้ไข: อ้างอิง logistics_picking_slips
            $table->foreignUuid('picking_slip_id')->constrained('logistics_picking_slips')->cascadeOnDelete();

            $table->foreignId('sales_order_item_id')->constrained('sales_order_items');

            $table->string('product_id');
            $table->integer('quantity_requested');
            $table->integer('quantity_picked')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        // ✅ แก้ไข: Drop ตารางใหม่
        Schema::dropIfExists('logistics_picking_slip_items');
    }
};
