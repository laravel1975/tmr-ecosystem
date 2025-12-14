<?php

namespace Tests\Feature\Logistics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use TmrEcosystem\Stock\Application\UseCases\ReceiveStockUseCase;
use TmrEcosystem\Stock\Application\DTOs\ReceiveStockData;
use TmrEcosystem\Stock\Domain\Events\StockReceived;
use TmrEcosystem\Logistics\Application\Listeners\AllocateBackorders;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderItemModel;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockLevelModel;
use TmrEcosystem\Inventory\Infrastructure\Persistence\database\factories\ItemFactory;
// (ปรับ Import ตาม Factory ที่มีจริงในโปรเจค)

class BackorderAllocationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_fires_event_when_stock_is_received()
    {
        Event::fake();

        // 1. Prepare Data
        $itemUuid = 'item-uuid-123';
        $locationUuid = 'loc-uuid-123';
        $companyId = 'comp-1';

        // Mock Dependencies (Repository/Service) หรือใช้ข้อมูลจริงผ่าน DB
        // ในที่นี้สมมติว่าเรียก UseCase โดยตรง
        $useCase = app(ReceiveStockUseCase::class);

        $data = new ReceiveStockData(
            itemUuid: $itemUuid,
            warehouseUuid: 'wh-1',
            locationUuid: $locationUuid,
            quantity: 100,
            userId: 'user-1',
            companyId: $companyId,
            reference: 'PO-TEST'
        );

        // 2. Action
        $useCase->__invoke($data);

        // 3. Assert Event Fired
        Event::assertDispatched(StockReceived::class, function ($event) use ($itemUuid, $locationUuid) {
            return $event->itemUuid === $itemUuid && $event->quantity === 100.0;
        });
    }

    /** @test */
    public function listener_creates_picking_slip_for_backorder()
    {
        // 1. Prepare Scenario: มี Order ค้างอยู่ (Backorder)
        $itemUuid = 'item-A';
        $companyId = 'comp-1';
        $locationUuid = 'loc-A';

        // Mock Inventory Item (เพื่อให้ lookup เจอ)
        // ... (Code สร้าง Inventory Item ลง DB) ...

        // สร้าง Sales Order ที่สถานะเป็น backorder
        $order = SalesOrderModel::factory()->create([
            'company_id' => $companyId,
            'stock_status' => 'backorder',
            'warehouse_id' => 'wh-1'
        ]);

        // สร้าง Item ใน Order ว่าต้องการ 10 ชิ้น แต่ยังไม่ได้ส่ง (qty_shipped = 0)
        SalesOrderItemModel::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => 'PART-A', // ต้องตรงกับ Item ที่ผูกกับ UUID
            'quantity' => 10,
            'qty_shipped' => 0
        ]);

        // สร้าง Stock Level ว่างๆ ไว้รอรับของ (หรือ Mock Repository)
        StockLevelModel::create([
            'uuid' => 'stock-1',
            'company_id' => $companyId,
            'item_uuid' => $itemUuid,
            'location_uuid' => $locationUuid,
            'quantity_on_hand' => 0,
            'quantity_soft_reserved' => 0
        ]);

        // 2. Action: จำลองว่า Listener ทำงาน (รับของเข้า 5 ชิ้น)
        $event = new StockReceived($itemUuid, $locationUuid, 5.0, $companyId);
        $listener = app(AllocateBackorders::class);
        $listener->handle($event);

        // 3. Assertions
        // A. ต้องมีการสร้าง Picking Slip ใหม่
        $this->assertDatabaseHas('logistics_picking_slips', [
            'order_id' => $order->id,
            'status' => 'pending'
        ]);

        // B. Picking Slip Item ต้องมียอด 5 (เพราะรับมาแค่ 5 แม้จะขอ 10)
        $this->assertDatabaseHas('logistics_picking_slip_items', [
            'quantity_requested' => 5
        ]);

        // C. Stock ต้องถูกจอง (Soft Reserved)
        $this->assertDatabaseHas('stock_levels', [
            'item_uuid' => $itemUuid,
            'quantity_soft_reserved' => 5
        ]);
    }
}
