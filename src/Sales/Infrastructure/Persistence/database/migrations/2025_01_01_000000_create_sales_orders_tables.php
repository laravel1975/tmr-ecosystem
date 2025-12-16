<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ตาราง Orders (หัวบิล)
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->uuid('id')->primary(); // ใช้ UUID เป็น Primary Key
            $table->string('order_number')->unique(); // เลขที่ใบสั่งซื้อที่มนุษย์อ่านรู้เรื่อง (เช่น SO-202501-001)
            $table->string('customer_id')->index(); // อ้างอิง Customer (อาจจะมาจาก Module CRM)

            // สถานะออเดอร์ (Draft, PendingReservation, Confirmed, Cancelled, Completed)
            $table->string('status')->default('draft');

            // ยอดรวม (ควรเก็บเพื่อ Query เร็วๆ แม้จะคำนวณจาก Items ได้)
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('THB');

            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes(); // เผื่อกู้คืน
        });

        // 2. ตาราง Order Items (รายการสินค้า)
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Internal ID
            // [FIX] เปลี่ยนชื่อ Foreign Key เป็น order_id ให้ตรงกับ Model SalesOrderModel
            $table->foreignUuid('order_id')->constrained('sales_orders')->cascadeOnDelete();

            $table->string('product_id'); // ID สินค้าจาก Inventory

            // --- Data Snapshot (สำคัญมาก!) ---
            $table->string('product_name'); // ชื่อสินค้า ณ ตอนขาย
            $table->decimal('unit_price', 12, 2); // ราคาต่อหน่วย ณ ตอนขาย
            // ----------------------------------

            $table->integer('quantity');
            $table->integer('qty_shipped')->default(0);
            $table->decimal('subtotal', 12, 2); // unit_price * quantity

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
    }
};
