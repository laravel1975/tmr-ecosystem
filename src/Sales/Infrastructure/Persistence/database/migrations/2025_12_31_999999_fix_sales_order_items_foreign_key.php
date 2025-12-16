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
        $columnName = 'sales_order_id';

        // ถ้าไม่มีตารางนี้ (เผื่อกรณีรัน Test แล้ว migrate ยังไม่ถึง) ให้ข้าม
        if (!Schema::hasTable($tableName)) {
            return;
        }

        // --- PART 1: Handle Foreign Key Dropping ---
        // เช็ค Driver ก่อน เพราะ SQLite ไม่มี information_schema
        if (DB::getDriverName() !== 'sqlite') {
            $fkName = 'sales_order_items_sales_order_id_foreign';

            // เช็คว่ามี Foreign Key นี้อยู่จริงไหม (สำหรับ MySQL/Postgres)
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
        }

        // --- PART 2: Drop & Recreate Column ---
        if (Schema::hasColumn($tableName, $columnName)) {
            Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                // SQLite: การ dropColumn จะทำการ recreate table อัตโนมัติและทิ้ง constraints เดิม
                // MySQL: จะ drop column และ fk ที่ผูกอยู่ออก
                $table->dropColumn($columnName);
            });
        }

        // --- PART 3: Create New Column ---
        Schema::table($tableName, function (Blueprint $table) use ($columnName) {
            $table->uuid($columnName)->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        // Optional: revert logic if needed
    }
};
