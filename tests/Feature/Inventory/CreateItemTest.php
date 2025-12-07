<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use TmrEcosystem\Inventory\Application\DTOs\ItemData;
use TmrEcosystem\Inventory\Application\UseCases\ManageItems\CreateItemUseCase;
use TmrEcosystem\Inventory\Domain\Exceptions\PartNumberAlreadyExistsException;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use TmrEcosystem\Shared\Domain\Models\Company;

class CreateItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // (Setup ข้อมูลเบื้องต้นถ้าจำเป็น เช่น User/Company)
        // $this->user = User::factory()->create();
    }

    public function test_use_case_can_create_item_successfully()
    {
        // 1. Arrange
        $company = Company::factory()->create();

        $dto = new ItemData(
            companyId: $company->id, // สมมติว่า id เป็น uuid หรือ string
            partNumber: 'SKU-TEST-001',
            name: 'Gaming Mouse',
            uom: 'PCS',
            averageCost: 1500.00,
            category: 'Electronics',
            description: 'High precision mouse'
        );

        // Resolve Use Case จาก Container (เพื่อให้ได้ Repository ตัวจริง)
        $useCase = app(CreateItemUseCase::class);

        // 2. Act
        $item = $useCase($dto);

        // 3. Assert
        $this->assertDatabaseHas('inventory_items', [
            'part_number' => 'SKU-TEST-001',
            'name' => 'Gaming Mouse',
            'company_id' => $company->id,
            'average_cost' => 1500.00
        ]);

        $this->assertEquals('SKU-TEST-001', $item->partNumber());
    }

    public function test_cannot_create_duplicate_part_number_in_same_company()
    {
        // 1. Arrange: สร้าง Item เดิมไว้ก่อน
        $company = Company::factory()->create();
        ItemModel::factory()->create([
            'company_id' => $company->id,
            'part_number' => 'DUPLICATE-001'
        ]);

        $dto = new ItemData(
            companyId: $company->id,
            partNumber: 'DUPLICATE-001', // ซ้ำ!
            name: 'Another Item',
            uom: 'PCS',
            averageCost: 100,
            category: 'General',
            description: null
        );

        $useCase = app(CreateItemUseCase::class);

        // 2. Act & Assert: คาดหวัง Domain Exception
        $this->expectException(PartNumberAlreadyExistsException::class);

        $useCase($dto);
    }
}
