<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_fulfillment_histories', function (Blueprint $table) {
            $table->id();

            // FK ไปที่ Sales Order Item (รายการสินค้าที่สั่ง)
            $table->uuid('sales_order_item_id')->index();

            // FK ไปที่ Delivery Note (ใบส่งของที่เป็นต้นเรื่อง)
            $table->uuid('delivery_note_id')->index();

            // เก็บจำนวนที่ถูกตัดยอดไปใน Transaction นี้ (เพื่อ Audit)
            $table->decimal('quantity_shipped', 10, 2);

            $table->timestamps();

            // [IMPORTANT] Composite Unique Index
            // หัวใจของ Idempotency: ห้ามคู่ (Item + DeliveryNote) ซ้ำกันเด็ดขาด
            // ถ้า Database เจอคู่ซ้ำ จะโยน Error (หรือเราเช็คก่อน insert)
            $table->unique(['sales_order_item_id', 'delivery_note_id'], 'sfh_dedup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_fulfillment_histories');
    }
};
