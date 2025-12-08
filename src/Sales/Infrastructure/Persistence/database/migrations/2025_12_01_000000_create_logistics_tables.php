<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ใบหยิบสินค้า (Picking Slip) - ใช้ในคลัง
        Schema::create('sales_picking_slips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('picking_number')->unique(); // e.g., PK-202511-001
            $table->foreignUuid('order_id')->constrained('sales_orders')->cascadeOnDelete();

            // Status: pending (รอหยิบ), assigned (มอบหมายแล้ว), done (หยิบเสร็จ), cancelled
            $table->string('status')->default('pending');

            $table->foreignId('picker_user_id')->nullable(); // คนที่ได้รับมอบหมายให้ไปหยิบ
            $table->timestamp('picked_at')->nullable(); // เวลาที่หยิบเสร็จ

            $table->text('note')->nullable();
            $table->timestamps();
        });

        // 2. ใบส่งของ (Delivery Note) - ใช้แปะหน้ากล่อง/ลูกค้าเซ็น
        Schema::create('sales_delivery_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('delivery_number')->unique(); // e.g., DO-202511-001
            $table->foreignUuid('order_id')->constrained('sales_orders');
            $table->foreignUuid('picking_slip_id')->nullable(); // เชื่อมโยงว่ามาจากการหยิบครั้งไหน

            // ข้อมูลการจัดส่ง (Snapshot จาก Customer ณ ตอนส่ง)
            $table->text('shipping_address');
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();

            // Status: draft, ready_to_ship, shipped, delivered, returned
            $table->string('status')->default('draft');

            $table->string('tracking_number')->nullable(); // เลขพัสดุ (Kerry, Flash, etc.)
            $table->string('carrier_name')->nullable(); // ชื่อขนส่ง

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_delivery_notes');
        Schema::dropIfExists('sales_picking_slips');
    }
};
