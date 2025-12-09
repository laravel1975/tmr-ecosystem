<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_delivery_notes', function (Blueprint $table) {
            // เพิ่มคอลัมน์ note เอาไว้เก็บเหตุผลการยกเลิก หรือหมายเหตุอื่นๆ
            $table->text('note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sales_delivery_notes', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
