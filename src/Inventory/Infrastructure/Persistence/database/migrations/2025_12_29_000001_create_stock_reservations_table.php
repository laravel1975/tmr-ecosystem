<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_item_id')->index();
            $table->uuid('warehouse_id')->index();
            $table->string('reference_id')->index()->comment('Order ID or Reference'); // Sales Order ID
            $table->decimal('quantity', 10, 2);
            $table->string('state')->default('soft_reserved'); // soft_reserved, hard_reserved, released, fulfilled
            $table->timestamp('expires_at')->nullable()->index(); // TTL for Soft Reserve
            $table->timestamps();

            // Optional: Foreign keys if cross-bounded context allows (usually loose coupling preferred)
            // $table->foreign('inventory_item_id')...
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_reservations');
    }
};
