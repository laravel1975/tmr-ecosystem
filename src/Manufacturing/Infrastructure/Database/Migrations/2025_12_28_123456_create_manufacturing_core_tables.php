<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TmrEcosystem\Shared\Domain\Models\Company; // ✅ ใช้ Model จาก Shared เท่านั้น

return new class extends Migration
{
    public function up(): void
    {
        // 1. Work Centers (เครื่องจักร/สถานีงาน)
        Schema::create('manufacturing_work_centers', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('code')->index();
            $table->string('name');
            $table->decimal('capacity_per_hour', 10, 2)->default(0);
            $table->decimal('cost_per_hour', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        // 2. BOM Header (สูตรการผลิต - หัวบิล)
        Schema::create('manufacturing_boms', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();

            // สินค้าที่ผลิตได้ (Finished Good) - อ้างอิง UUID จาก Inventory
            $table->uuid('item_uuid')->index();

            $table->string('code')->index();
            $table->string('name');
            $table->string('version')->default('1.0');
            $table->decimal('output_quantity', 10, 4)->default(1); // ได้ผลผลิตกี่ชิ้นต่อ 1 สูตร
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'item_uuid', 'version']);
        });

        // 3. BOM Components (วัตถุดิบที่ใช้ในสูตร)
        Schema::create('manufacturing_bom_components', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('bom_uuid')->constrained('manufacturing_boms', 'uuid')->cascadeOnDelete();

            // วัตถุดิบ (Raw Material)
            $table->uuid('component_item_uuid')->index();

            $table->decimal('quantity', 15, 4); // ปริมาณที่ใช้
            $table->decimal('waste_percent', 5, 2)->default(0); // % การสูญเสีย
            $table->timestamps();
        });

        // 4. Production Orders (ใบสั่งผลิต)
        Schema::create('manufacturing_production_orders', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();

            $table->string('order_number')->index();
            $table->uuid('item_uuid'); // ผลิตอะไร
            $table->uuid('bom_uuid')->nullable(); // ใช้สูตรไหน

            $table->decimal('planned_quantity', 15, 4);
            $table->decimal('produced_quantity', 15, 4)->default(0);

            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();

            $table->string('status')->default('draft')->index(); // draft, planned, in_progress, completed

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_production_orders');
        Schema::dropIfExists('manufacturing_bom_components');
        Schema::dropIfExists('manufacturing_boms');
        Schema::dropIfExists('manufacturing_work_centers');
    }
};
