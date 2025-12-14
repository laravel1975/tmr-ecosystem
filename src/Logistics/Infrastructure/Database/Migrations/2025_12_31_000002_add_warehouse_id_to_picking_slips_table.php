<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('logistics_picking_slips', function (Blueprint $table) {
            // เพิ่ม warehouse_id (UUID) เพื่อระบุว่าต้องหยิบของจากคลังไหน
            $table->uuid('warehouse_id')->nullable()->after('order_id')->index();

            // หมายเหตุ: ไม่ได้ใส่ constrained('warehouses') แบบ Hard strict
            // เผื่อกรณี Cross-module database แต่ถ้าอยู่ DB เดียวกันสามารถ uncomment บรรทัดล่างได้
            // $table->foreign('warehouse_id')->references('uuid')->on('warehouses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logistics_picking_slips', function (Blueprint $table) {
            $table->dropColumn('warehouse_id');
        });
    }
};
