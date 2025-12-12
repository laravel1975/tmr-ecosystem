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
            $table->text('note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_delivery_notes', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
