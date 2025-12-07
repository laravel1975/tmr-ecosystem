<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // เพิ่มสถานะสต็อก: pending (รอจอง), reserved (จองแล้ว), backorder (ของขาด), fulfilled (ตัดจ่ายแล้ว)
            // วางไว้หลัง column 'status'
            $table->string('stock_status')->default('pending')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('stock_status');
        });
    }
};
