<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ตาราง Vendors (สมมติว่า Vendors ใช้ id ปกติ ถ้าใช้ uuid ก็ต้องแก้แบบเดียวกัน)
        if (!Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id(); // ถ้า Vendors มี id ก็ใช้ foreignId ได้
                $table->uuid('uuid')->unique();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('tax_id')->nullable();
                $table->text('address')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->integer('credit_term_days')->default(30);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. ตาราง Purchase Orders
        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('document_number')->unique();
                $table->foreignId('vendor_id')->constrained('vendors');
                $table->foreignId('created_by')->constrained('users');
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->string('status')->default('draft');
                $table->text('notes')->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. ตาราง Purchase Order Items (จุดที่เกิด Error)
        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

                // --- แก้ไขตรงนี้ ---
                // เปลี่ยนจาก foreignId เป็น foreignUuid
                // และระบุ column ปลายทางเป็น 'uuid' (ไม่ใช่ default 'id')
                if (Schema::hasTable('inventory_items')) {
                    $table->foreignUuid('item_id') // สร้าง column item_id เป็น char(36)
                        ->constrained('inventory_items', 'uuid'); // Link ไปที่ inventory_items.uuid
                } else {
                    $table->uuid('item_id'); // สร้างเป็น uuid รอไว้ก่อนถ้ายังไม่มีตาราง
                }
                // ------------------

                $table->decimal('quantity', 10, 2);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('total_price', 15, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('vendors');
    }
};
