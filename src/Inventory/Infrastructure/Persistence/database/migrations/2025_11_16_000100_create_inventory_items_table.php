<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TmrEcosystem\Shared\Domain\Models\Company;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {

            // --- 1. Primary & Foreign Keys ---
            $table->uuid('uuid')->primary();

            // (สำคัญ) นี่คือ Foreign Key สำหรับ Multi-Tenancy
            // ที่เราวิเคราะห์ได้จาก CompanyScope ใน Asset.php และ EmployeeProfile.php
            $table->foreignIdFor(Company::class)
                  ->constrained()
                  ->cascadeOnDelete();

            // --- 2. Core Item Fields (จาก $fillable) ---
            $table->string('part_number'); // (Req A)
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable()->index(); // (Req A) (เพิ่ม Index สำหรับค้นหา)

            // (Req G) (อิงจาก $casts['average_cost' => 'decimal:4'])
            // (15, 4) หมายถึง 15 หลักทั้งหมด, 4 หลักหลังจุดทศนิยม
            $table->decimal('average_cost', 15, 4)->default(0.0000);

            $table->string('uom'); // (Req Core) (Unit of Measure)

            // (เพิ่ม Field อื่นๆ ที่จำเป็นสำหรับ Req E: Replenishment)
            // $table->integer('min_stock')->default(0);
            // $table->integer('max_stock')->default(0);

            // (ถ้า Supplier อยู่ใน Context เดียวกัน)
            // $table->foreignUuid('supplier_id')->nullable()->constrained('inventory_suppliers');

            // --- 3. Timestamps (จาก HasFactory และ SoftDeletes) ---
            $table->timestamps(); // (created_at, updated_at)
            $table->softDeletes(); // (deleted_at) (อิงจาก SoftDeletes Trait)

            // --- 4. Constraints ---
            // (สำคัญ) Part Number ควรจะซ้ำกันได้ถ้
            // าอยู่คนละบริษัท
            // แต่ห้ามซ้ำกันภายในบริษัทเดียวกัน
            $table->unique(['company_id', 'part_number']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
