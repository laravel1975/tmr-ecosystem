<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TmrEcosystem\Shared\Domain\Models\Company;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id(); // (Primary Key ฐานข้อมูล)
            $table->uuid('uuid')->unique(); // (Domain ID)

            // --- 1. Keys (Tenant & Contexts) ---

            // (Key สำหรับ Multi-Tenancy)
            $table->foreignIdFor(Company::class)
                  ->constrained()
                  ->cascadeOnDelete();

            // (Key ที่อ้างอิงถึง Bounded Context อื่น)
            // (เราไม่ใช้ constrained() เพราะอยู่คนละ Context)
            $table->uuid('item_uuid')->index();
            $table->uuid('warehouse_uuid')->index();

            // --- 2. State (ข้อมูลที่ Context นี้รับผิดชอบ) ---
            $table->decimal('quantity_on_hand', 15, 4)->default(0.0000);
            $table->decimal('quantity_reserved', 15, 4)->default(0.0000);

            // --- 3. Timestamps ---
            $table->timestamps();
            $table->softDeletes();

            // --- 4. Constraints (กฎ) ---
            // (ห้ามมี "ยอด" ของ Item+Warehouse ซ้ำกันใน Company เดียว)
            $table->unique(
                ['company_id', 'item_uuid', 'warehouse_uuid'],
                'stock_level_unique_item_warehouse' // (ตั้งชื่อ Constraint ให้อ่านง่าย)
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
