<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TmrEcosystem\Shared\Domain\Models\Company;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ปรับปรุง Inventory Items (Req 1: Is Component)
        Schema::table('inventory_items', function (Blueprint $table) {
            // is_component: เป็นวัตถุดิบได้หรือไม่
            $table->boolean('is_component')->default(true)->after('description');
            // is_manufactured: เป็นสินค้าที่ผลิตได้หรือไม่ (Optional: เพื่อกรอง Finished Good)
            $table->boolean('is_manufactured')->default(true)->after('is_component');
        });

        // 2. ปรับปรุง BOM Header (Req 2: BOM Type)
        Schema::table('manufacturing_boms', function (Blueprint $table) {
            // type: 'manufacture' (ผลิตปกติ), 'kit' (ชุดขาย/Phantom)
            $table->string('type')->default('manufacture')->after('item_uuid');
        });

        // 3. สร้างตาราง By-products (Req 3: ผลพลอยได้)
        Schema::create('manufacturing_bom_byproducts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('bom_uuid')->constrained('manufacturing_boms', 'uuid')->cascadeOnDelete();

            // สินค้าที่เป็นผลพลอยได้ (เช่น เศษเหล็ก)
            $table->uuid('item_uuid')->index();
            // $table->foreign('item_uuid')->references('uuid')->on('inventory_items');

            $table->decimal('quantity', 15, 4); // จำนวนที่ได้
            $table->string('uom')->nullable(); // หน่วยนับ (ถ้าต่างจาก Item หลัก)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_bom_byproducts');

        Schema::table('manufacturing_boms', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['is_component', 'is_manufactured']);
        });
    }
};
