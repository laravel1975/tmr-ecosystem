<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_return_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('return_number')->unique(); // RN-XXXXXX
            $table->foreignUuid('order_id')->nullable(); // อ้างอิง Sales Order (ข้าม BC อนุโลมให้ใช้ ID ได้)

            // ✅ แก้ไข: อ้างอิง Picking Slip ของ Logistics (เปลี่ยนชื่อตารางแล้ว)
            $table->foreignUuid('picking_slip_id')
                  ->nullable()
                  ->constrained('logistics_picking_slips');

            // ✅ แก้ไข: อ้างอิง Delivery Note ของ Logistics (เปลี่ยนชื่อตารางแล้ว)
            $table->foreignUuid('delivery_note_id')
                  ->nullable()
                  ->constrained('logistics_delivery_notes')
                  ->onDelete('set null');

            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('logistics_return_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('return_note_id')->constrained('logistics_return_notes')->cascadeOnDelete();
            $table->string('product_id'); // Part Number
            $table->integer('quantity'); // จำนวนที่ต้องคืน
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_return_note_items');
        Schema::dropIfExists('logistics_return_notes');
    }
};
