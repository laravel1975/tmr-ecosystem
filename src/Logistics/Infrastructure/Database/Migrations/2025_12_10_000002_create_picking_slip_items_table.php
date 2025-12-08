<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_picking_slip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('picking_slip_id')->constrained('sales_picking_slips')->cascadeOnDelete();

            // เชื่อมกับรายการใน Sales Order เพื่ออ้างอิง
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items');

            // Snapshot ข้อมูลสินค้า (เผื่อ Inventory เปลี่ยน)
            $table->string('product_id'); // SKU/Part Number

            // หัวใจสำคัญ: แยกยอดที่ "ต้องหยิบ" กับ "หยิบได้จริง"
            $table->integer('quantity_requested'); // ยอดที่ต้องหยิบในรอบนี้
            $table->integer('quantity_picked')->default(0); // ยอดที่หยิบได้จริง

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_picking_slip_items');
    }
};
