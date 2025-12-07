<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturing_production_orders', function (Blueprint $table) {
            // เก็บที่มาของใบสั่งผลิต (เช่น 'manual', 'sales_order', 'stock_replenishment')
            $table->string('origin_type')->default('manual')->after('status')->index();

            // เก็บ UUID ของเอกสารต้นทาง (เช่น Sales Order UUID)
            $table->uuid('origin_uuid')->nullable()->after('origin_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing_production_orders', function (Blueprint $table) {
            $table->dropColumn(['origin_type', 'origin_uuid']);
        });
    }
};
