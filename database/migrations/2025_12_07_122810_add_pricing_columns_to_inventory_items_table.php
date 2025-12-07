<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // 1. sale_price (ราคาขาย): เก็บเป็น integer (สตางค์)
            // default 0 หมายถึง 0.00 บาท
            $table->unsignedBigInteger('sale_price')->default(0)->after('average_cost');

            // 2. cost_price (ราคาทุนมาตรฐาน): เก็บเป็น integer (สตางค์)
            // แยกจาก average_cost ที่เป็น Dynamic calculation
            $table->unsignedBigInteger('cost_price')->default(0)->after('sale_price');

            // หมายเหตุ: average_cost ของเดิมเป็น decimal(15,4)
            // หากคุณต้องการเปลี่ยน average_cost ให้ใช้ MoneyCast ด้วย
            // คุณต้องเขียน script แปลงค่า * 100 แล้วเปลี่ยน type เป็น BigInteger
            // แต่ในเฟสนี้ แนะนำให้ปล่อย average_cost ไว้แบบเดิมก่อนถ้ายังไม่ได้ใช้ Cast กับตัวนี้
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'cost_price']);
        });
    }
};
