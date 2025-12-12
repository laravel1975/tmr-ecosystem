<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
// ✅ ต้องแก้ Use Model ให้ถูกด้วย (ถ้ามี Model ใหม่)
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ แก้ไข: ชี้ไปที่ logistics_delivery_notes
        Schema::table('logistics_delivery_notes', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('id');
        });

        // Generate Token
        // หมายเหตุ: ต้องแน่ใจว่า Model DeliveryNote ชี้ไปที่ table 'logistics_delivery_notes' แล้ว (ตามที่แก้ใน Turn ก่อนหน้า)
        $notes = DeliveryNote::whereNull('tracking_token')->get();
        foreach ($notes as $note) {
            $note->update(['tracking_token' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('logistics_delivery_notes', function (Blueprint $table) {
            $table->dropColumn('tracking_token');
        });
    }
};
