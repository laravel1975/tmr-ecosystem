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
            $table->foreignUuid('order_id')->nullable(); // อ้างอิง Order
            $table->foreignUuid('picking_slip_id')->nullable(); // อ้างอิงใบหยิบเดิม
            // ✅ เพื่อผูกกับใบส่งของที่ถูกยกเลิก
            $table->foreignUuid('delivery_note_id')
                  ->nullable()
                  ->constrained('sales_delivery_notes')
                  ->onDelete('set null');
            $table->string('status')->default('pending'); // pending (รอนำเก็บ), completed (เก็บแล้ว)
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
