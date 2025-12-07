<?php

namespace Tests\Feature\Inventory;

// --- (นี่คือบรรทัดที่แก้ไข) ---
use Illuminate\Foundation\Testing\RefreshDatabase;
// (ลบ 'Z' ที่ผิดออก และใช้ 'F' ที่ถูกต้อง)
// --- (จบส่วนที่แก้ไข) ---

use Tests\TestCase;
use TmrEcosystem\IAM\Domain\Models\User; // (อิงจาก IAM Context)
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\Item as ItemModel;
use TmrEcosystem\Shared\Domain\Models\Company;

/**
 * นี่คือ Feature Test สำหรับ "Create Item"
 */
class ItemCreationTest extends TestCase
{
    use RefreshDatabase; // (ล้างฐานข้อมูลทุกครั้งที่รันเทส)

    protected User $user;
    protected Company $company;

    /**
     * (ตั้งค่า) สร้าง Company และ User ที่ล็อกอินก่อนทุกเทส
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. (อิงจาก Multi-Tenant) สร้าง Company
        $this->company = Company::factory()->create();

        // 2. (อิงจาก IAM) สร้าง User และผูกกับ Company
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    // --- (Test Case 1: สร้างสำเร็จ) ---
    public function test_user_can_create_a_new_item_successfully(): void
    {
        // 1. (Arrange) ข้อมูลที่จะส่ง (อิงจาก CreateItemRequest)
        $postData = [
            'part_number' => 'PN-12345',
            'name' => 'Test Item',
            'uom' => 'EA',
            'category' => 'Mechanical',
            'average_cost' => 100.50,
            'description' => 'A test description.',
        ];

        // 2. (Act) จำลองการยิง POST โดยล็อกอินเป็น User ของเรา
        $response = $this->actingAs($this->user)
                         ->post(route('inventory.items.store'), $postData);

        // 3. (Assert) ตรวจสอบผลลัพธ์

        // 3.1) ตรวจสอบว่า Redirect กลับไปหน้า index (ตาม ItemController)
        $response->assertRedirectToRoute('inventory.items.index');

        // 3.2) ตรวจสอบว่ามีข้อความ "success" (ตาม ItemController)
        $response->assertSessionHas('success');

        // 3.3) (สำคัญที่สุด) ตรวจสอบว่าข้อมูลถูกบันทึกลง DB
        $this->assertDatabaseHas('inventory_items', [
            'company_id' => $this->company->id, // (เช็กว่า company_id ถูกต้อง)
            'part_number' => 'PN-12345',
            'name' => 'Test Item',
            'average_cost' => 100.50,
        ]);
    }

    // --- (Test Case 2: Validation พลาด) ---
    public function test_item_creation_fails_if_part_number_is_missing(): void
    {
        // 1. (Arrange) ข้อมูลไม่ครบ (ขาด part_number)
        $postData = [
            'name' => 'Test Item',
            'uom' => 'EA',
            'category' => 'Mechanical',
            'average_cost' => 100.50,
        ];

        // 2. (Act) ยิง POST
        $response = $this->actingAs($this->user)
                         ->post(route('inventory.items.store'), $postData);

        // 3. (Assert)

        // 3.1) ตรวจสอบว่า Validation แจ้ง Error ที่ช่อง 'part_number'
        $response->assertSessionHasErrors('part_number');

        // 3.2) ตรวจสอบว่า "ไม่มี" ข้อมูลถูกบันทึกลง DB
        $this->assertDatabaseMissing('inventory_items', [
            'name' => 'Test Item',
        ]);
    }

    // --- (Test Case 3: Validation Unique พลาด) ---
    public function test_item_creation_fails_if_part_number_already_exists_in_company(): void
    {
        // 1. (Arrange) สร้าง Item ชิ้นแรกไว้ใน DB ก่อน
        ItemModel::factory()->create([
            'company_id' => $this->company->id,
            'part_number' => 'PN-EXISTING',
        ]);

        // 2. (Arrange) ข้อมูลที่จะส่ง (ใช้ part_number ซ้ำ)
        $postData = [
            'part_number' => 'PN-EXISTING', // (ซ้ำกับชิ้นบน)
            'name' => 'Duplicate Item',
            'uom' => 'EA',
            'category' => 'Test',
            'average_cost' => 10,
        ];

        // 3. (Act) ยิง POST
        $response = $this->actingAs($this->user)
                         ->post(route('inventory.items.store'), $postData);

        // 4. (Assert)

        // 4.1) ตรวจสอบว่า Validation แจ้ง Error ที่ช่อง 'part_number'
        // (นี่คือการพิสูจน์ว่า CreateItemRequest ทำงานถูกต้อง)
        $response->assertSessionHasErrors('part_number');

        // 4.2) ตรวจสอบว่ามี Item ใน DB แค่ 1 ชิ้น (ชิ้นเดิม)
        $this->assertDatabaseCount('inventory_items', 1);
    }
}
