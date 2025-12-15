<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'sales_order_items';
        $fkName = 'sales_order_items_sales_order_id_foreign';

        // 1. เช็คว่ามี Foreign Key นี้อยู่จริงไหม (รองรับ MySQL)
        // ถ้าใช้ DB อื่นอาจต้องปรับ Query แต่ส่วนใหญ่โปรเจกต์นี้ใช้ MySQL
        $hasFk = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_NAME = ?
            AND CONSTRAINT_NAME = ?
            AND TABLE_SCHEMA = DATABASE()
        ", [$tableName, $fkName]);

        if (!empty($hasFk)) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['sales_order_id']);
            });
        }

        // 2. เช็คว่ามีคอลัมน์ sales_order_id ไหม ถ้ามีให้ลบออก (เพื่อสร้างใหม่ให้ถูก Type)
        if (Schema::hasColumn($tableName, 'sales_order_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                // ต้องระวัง: ถ้ายังมี FK ค้างอยู่ที่ไม่ได้ชื่อตาม Convention อาจจะลบไม่ผ่าน
                // แต่จากขั้นตอนที่ 1 เราพยายามลบไปแล้ว หวังว่าจะผ่าน
                $table->dropColumn('sales_order_id');
            });
        }

        // 3. สร้างใหม่ให้เป็น UUID ที่ถูกต้อง
        Schema::table($tableName, function (Blueprint $table) {
            $table->uuid('sales_order_id')->nullable()->after('id')->index();
            // ใส่ nullable ไว้ก่อนเผื่อมี data เก่า, แต่ถ้า data ใหม่ควร require
        });
    }

    public function down(): void
    {
        // ไม่ต้องทำอะไร หรือถ้าจะทำก็คือ drop column
    }
};
