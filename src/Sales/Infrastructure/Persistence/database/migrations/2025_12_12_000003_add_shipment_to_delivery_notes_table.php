<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_delivery_notes', function (Blueprint $table) {
            // เชื่อมกับรอบส่ง (Shipment)
            $table->foreignUuid('shipment_id')->nullable()->constrained('logistics_shipments')->nullOnDelete()->after('order_id');

            // ระบุประเภทการจัดส่ง: fleet (รถเรา/รถเช่า), platform (Shopee/Lazada/Kerry), customer (ลูกค้ามารับเอง)
            $table->string('carrier_type')->default('fleet')->after('shipment_id');

            // ลำดับการส่งในรอบนั้นๆ (Stop Sequence) 1, 2, 3...
            $table->integer('stop_sequence')->nullable()->after('carrier_type');
        });
    }

    public function down(): void
    {
        Schema::table('sales_delivery_notes', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn(['shipment_id', 'carrier_type', 'stop_sequence']);
        });
    }
};
