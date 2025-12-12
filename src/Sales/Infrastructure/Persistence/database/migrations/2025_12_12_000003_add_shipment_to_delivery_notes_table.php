<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ แก้ไข: ชี้ไปที่ logistics_delivery_notes
        Schema::table('logistics_delivery_notes', function (Blueprint $table) {

            $table->foreignUuid('shipment_id')
                  ->nullable()
                  ->constrained('logistics_shipments')
                  ->nullOnDelete()
                  ->after('order_id');

            $table->string('carrier_type')->default('fleet')->after('shipment_id');
            $table->integer('stop_sequence')->nullable()->after('carrier_type');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_delivery_notes', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn(['shipment_id', 'carrier_type', 'stop_sequence']);
        });
    }
};
