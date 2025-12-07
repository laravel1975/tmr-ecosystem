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
        Schema::create('warehouses', function (Blueprint $table) {
            // (1) Primary Key แบบ Auto-incrementing
            $table->id();

            // (2) Domain ID
            $table->uuid('uuid')->unique();

            // (3) Foreign Key สำหรับ Multi-Tenancy (เหมือน Items)
            $table->foreignIdFor(Company::class)
                  ->constrained()
                  ->cascadeOnDelete();

            // (4) Core Fields (จาก Blueprint)
            $table->string('name'); // (เช่น "Main Warehouse")
            $table->string('code'); // (เช่น "MAIN", "WH-01")
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true); // (สถานะ)

            // (5) Timestamps (Soft Delete)
            $table->timestamps();
            $table->softDeletes();

            // (6) Constraint (ห้ามซ้ำภายใน Company เดียวกัน)
            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
