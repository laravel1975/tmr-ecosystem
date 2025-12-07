<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TmrEcosystem\Inventory\Application\DTOs\ItemData;
use TmrEcosystem\Inventory\Application\UseCases\ManageItems\UpdateItemUseCase;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use TmrEcosystem\Shared\Domain\Models\Company;

class UpdateItemTest extends TestCase
{
    use RefreshDatabase;

    // ✅ แก้ชื่อฟังก์ชันให้ขึ้นต้นด้วย test_ (และลบ @test ออก)
    public function test_it_can_update_item_details_successfully()
    {
        // 1. Arrange: สร้างข้อมูลเดิม
        $company = Company::factory()->create();
        $originalItem = ItemModel::factory()->create([
            'company_id' => $company->id,
            'part_number' => 'OLD-PART-001',
            'name' => 'Old Name',
            'average_cost' => 100.00
        ]);

        // เตรียมข้อมูลใหม่ (DTO)
        $newData = new ItemData(
            companyId: $company->id,
            partNumber: 'NEW-PART-001', // เปลี่ยน Part Number
            name: 'New Name',           // เปลี่ยนชื่อ
            uom: 'SET',
            averageCost: 200.50,        // เปลี่ยนราคา
            category: 'Updated Cat',
            description: 'Updated Desc'
        );

        $useCase = app(UpdateItemUseCase::class);

        // 2. Act
        $updatedItem = $useCase($originalItem->uuid, $newData);

        // 3. Assert: เช็ก Database ว่าเปลี่ยนจริงไหม
        $this->assertDatabaseHas('inventory_items', [
            'uuid' => $originalItem->uuid,
            'part_number' => 'NEW-PART-001',
            'name' => 'New Name',
            'average_cost' => 200.5000
        ]);

        // เช็ก Object ที่ Return กลับมา
        $this->assertEquals('NEW-PART-001', $updatedItem->partNumber());
    }
}
