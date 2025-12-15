<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // เพิ่ม salesperson_id (อ้างอิง users table)
            // ใส่ nullable ไว้ก่อนสำหรับข้อมูลเก่า
            $table->foreignId('salesperson_id')->nullable()->after('customer_id')->index();

            // ถ้าต้องการ FK Constraint (แนะนำ)
            // $table->foreign('salesperson_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('salesperson_id');
        });
    }
};
