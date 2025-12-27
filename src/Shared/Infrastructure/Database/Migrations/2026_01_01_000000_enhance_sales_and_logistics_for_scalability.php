<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ปรับปรุง Sales Orders: เพิ่ม Snapshot ลูกค้า (แก้ปัญหา Cross-Context Trap)
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'customer_snapshot')) {
                // เก็บ JSON ข้อมูลลูกค้า ณ เวลาสั่งซื้อ (Name, Tax ID, Address)
                $table->json('customer_snapshot')->nullable()->after('customer_id');
            }
        });

        // 2. ปรับปรุง Sales Order Items: รองรับ Partial Shipping ละเอียด
        Schema::table('sales_order_items', function (Blueprint $table) {
            // qty_shipped มีแล้วจาก migration ก่อนหน้า

            if (!Schema::hasColumn('sales_order_items', 'qty_reserved')) {
                // จำนวนที่จองไว้ (Soft Reserve) แต่ยังไม่ได้ส่ง
                $table->decimal('qty_reserved', 12, 2)->default(0)->after('quantity');
            }
            if (!Schema::hasColumn('sales_order_items', 'qty_cancelled')) {
                // จำนวนที่ยกเลิก (กรณีส่งไม่ครบแล้วลูกค้าไม่เอาของเหลือ)
                $table->decimal('qty_cancelled', 12, 2)->default(0)->after('qty_shipped');
            }
        });

        // 3. ปรับปรุง Picking Slips: เพิ่ม Snapshot ที่อยู่จัดส่ง (Decouple จาก Sales)
        Schema::table('logistics_picking_slips', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_picking_slips', 'shipping_address_snapshot')) {
                // เก็บที่อยู่จัดส่งที่จะใช้แปะหน้ากล่อง (ไม่ต้อง join กลับไปหา Order/Customer)
                $table->json('shipping_address_snapshot')->nullable()->after('order_id');
            }

            // เพิ่ม Idempotency Key เพื่อป้องกันการสร้างใบหยิบซ้ำ
            if (!Schema::hasColumn('logistics_picking_slips', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('logistics_picking_slips', function (Blueprint $table) {
            $table->dropColumn(['shipping_address_snapshot', 'idempotency_key']);
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn(['qty_reserved', 'qty_cancelled']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('customer_snapshot');
        });
    }
};
