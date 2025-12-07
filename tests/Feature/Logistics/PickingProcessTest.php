<?php

namespace Tests\Feature\Logistics;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
// ✅ เพิ่ม Import SalesOrderItemModel
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderItemModel;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use TmrEcosystem\Shared\Domain\Models\Company;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockLevelModel;

class PickingProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_confirm_picking_and_commit_stock()
    {
        // 1. Arrange: เตรียมข้อมูล
        $user = User::factory()->create();
        $company = Company::factory()->create(['id' => $user->company_id]);

        // สร้างสินค้า Inventory
        $item = ItemModel::factory()->create([
            'company_id' => $company->id,
            'part_number' => 'TEST-ITEM-001',
            'uuid' => 'item-uuid-123'
        ]);

        // สร้าง Stock
        StockLevelModel::create([
            'uuid' => 'stock-uuid-123',
            'company_id' => $company->id,
            'item_uuid' => $item->uuid,
            'warehouse_uuid' => 'wh-uuid-123',
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'quantity_soft_reserved' => 2
        ]);

        // สร้าง Order Head
        $order = SalesOrderModel::create([
            'id' => 'order-uuid-1',
            'order_number' => 'SO-001',
            'customer_id' => 'cust-1',
            'company_id' => $company->id,
            'warehouse_id' => 'wh-uuid-123',
            'status' => 'confirmed'
        ]);

        // ✅✅ FIX: สร้าง Order Item จริงๆ เพื่อให้มี ID อ้างอิง
        $salesOrderItem = SalesOrderItemModel::create([
            'order_id' => $order->id,
            'product_id' => 'TEST-ITEM-001',
            'product_name' => 'Test Product Name', // ต้องใส่เพราะ Migration บังคับ
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
            'qty_shipped' => 0
        ]);

        $picking = PickingSlip::create([
            'id' => 'pick-uuid-1',
            'picking_number' => 'PK-001',
            'order_id' => $order->id,
            'status' => 'pending'
        ]);

        $pickItem = PickingSlipItem::create([
            'picking_slip_id' => $picking->id,
            // ✅✅ FIX: ใช้ ID จริงที่เพิ่งสร้าง
            'sales_order_item_id' => $salesOrderItem->id,
            'product_id' => 'TEST-ITEM-001',
            'quantity_requested' => 2,
            'quantity_picked' => 0
        ]);

        // 2. Act: ยิง Request
        $response = $this->actingAs($user)
            ->post(route('logistics.picking.confirm', $picking->id), [
                'items' => [
                    [
                        'id' => $pickItem->id,
                        'qty_picked' => 2
                    ]
                ],
                'create_backorder' => false
            ]);

        // 3. Assert
        $response->assertRedirect(route('logistics.picking.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sales_picking_slips', [
            'id' => $picking->id,
            'status' => 'done'
        ]);

        $this->assertDatabaseHas('stock_levels', [
            'item_uuid' => $item->uuid,
            'quantity_reserved' => 2,
        ]);
    }
}
