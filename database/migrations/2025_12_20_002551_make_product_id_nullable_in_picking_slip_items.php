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
        Schema::table('logistics_picking_slip_items', function (Blueprint $table) {
            $table->string('product_id')->nullable()->change();
            // หรือเป็น uuid แล้วแต่ type เดิม
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('picking_slip_items', function (Blueprint $table) {
            //
        });
    }
};
