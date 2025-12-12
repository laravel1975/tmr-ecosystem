<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ใบหยิบสินค้า (Picking Slip)
        Schema::create('logistics_picking_slips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('picking_number')->unique(); // e.g., PK-202511-001

            // ยังคงต้อง refer ไปที่ sales_orders แต่ในระดับ Database ยอมรับได้ (หรือจะเก็บเป็น string order_id เพื่อ decouple ก็ได้)
            // ในที่นี้ขอใช้ foreignUuid เพื่อความสมบูรณ์ของ referential integrity ใน Monolith
            $table->foreignUuid('order_id')->constrained('sales_orders')->cascadeOnDelete();

            $table->string('status')->default('pending'); // pending, assigned, picked, cancelled
            $table->foreignId('picker_user_id')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // 2. รายการในใบหยิบ (Picking Slip Items)
        Schema::create('logistics_picking_slip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('picking_slip_id')->constrained('logistics_picking_slips')->cascadeOnDelete();

            // Refer ไปที่ Sales Order Item และ Product
            $table->unsignedBigInteger('sales_order_item_id');
            $table->string('product_id');

            $table->decimal('quantity_requested', 10, 2);
            $table->decimal('quantity_picked', 10, 2)->default(0);

            $table->timestamps();
        });

        // 3. ใบส่งของ (Delivery Note)
        Schema::create('logistics_delivery_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('delivery_number')->unique(); // e.g., DO-202511-001

            $table->foreignUuid('order_id')->constrained('sales_orders');
            $table->foreignUuid('picking_slip_id')->nullable()->constrained('logistics_picking_slips');

            // Data Snapshot (สำคัญมากสำหรับการแยก Service)
            $table->text('shipping_address');
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();

            $table->string('status')->default('draft'); // draft, ready_to_ship, shipped, delivered
            $table->string('tracking_number')->nullable();
            $table->string('carrier_name')->nullable();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_delivery_notes');
        Schema::dropIfExists('logistics_picking_slip_items');
        Schema::dropIfExists('logistics_picking_slips');
    }
};
