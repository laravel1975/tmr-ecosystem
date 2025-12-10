<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_delivery_notes', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('id');
        });

        // Generate Token ย้อนหลังให้ข้อมูลเดิม (Optional)
        $notes = DeliveryNote::whereNull('tracking_token')->get();
        foreach ($notes as $note) {
            $note->update(['tracking_token' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('sales_delivery_notes', function (Blueprint $table) {
            $table->dropColumn('tracking_token');
        });
    }
};
