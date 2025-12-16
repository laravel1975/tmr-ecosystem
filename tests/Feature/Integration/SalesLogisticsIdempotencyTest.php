<?php

namespace Tests\Feature\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderItemModel;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Logistics\Domain\Events\DeliveryNoteUpdated;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Application\Listeners\UpdateSalesOrderStatusOnDelivery;
use TmrEcosystem\Logistics\Application\Listeners\CreateLogisticsDocuments;
use TmrEcosystem\Sales\Application\DTOs\ShippedItemDto;
use TmrEcosystem\Sales\Application\Contracts\ShippedItemProviderInterface;
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
use TmrEcosystem\Sales\Application\DTOs\OrderItemSnapshotDto;
use TmrEcosystem\Logistics\Domain\Services\OrderFulfillmentService;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Services\StockPickingService;

class SalesLogisticsIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_fulfillment_is_idempotent_on_duplicate_events()
    {
        // 1. Arrange
        $orderId = (string) Str::uuid();
        $order = new SalesOrderModel();
        $order->id = $orderId;
        $order->order_number = 'SO-TEST-001';
        $order->status = 'confirmed';
        $order->customer_id = (string) Str::uuid();
        $order->company_id = (string) Str::uuid();
        $order->salesperson_id = (string) Str::uuid();
        $order->warehouse_id = (string) Str::uuid();
        $order->total_amount = 1000;
        $order->save();

        $itemId = (string) Str::uuid();
        $item = new SalesOrderItemModel();
        $item->id = $itemId;
        // [FIX] ใช้ order_id แทน sales_order_id
        $item->order_id = $orderId;
        $item->product_id = (string) Str::uuid();
        $item->product_name = 'Test Product Item';
        $item->quantity = 10;
        $item->qty_shipped = 0;
        $item->unit_price = 100;
        $item->save();

        $deliveryNote = new DeliveryNote();
        $deliveryNote->id = (string) Str::uuid();
        $deliveryNote->delivery_number = 'DN-001';
        $deliveryNote->order_id = $orderId;
        $deliveryNote->picking_slip_id = 'PS-001';
        $deliveryNote->status = 'delivered';

        $this->mock(ShippedItemProviderInterface::class, function ($mock) use ($itemId) {
            $mock->shouldReceive('getByPickingSlipId')
                ->with('PS-001')
                ->andReturn([
                    new ShippedItemDto(
                        sales_order_item_id: $itemId,
                        quantity_picked: 5.0
                    )
                ]);
        });

        $event = new DeliveryNoteUpdated($deliveryNote);
        $listener = app(UpdateSalesOrderStatusOnDelivery::class);

        // 2. Act (First Run)
        $listener->handle($event);

        $this->assertDatabaseHas('sales_order_items', [
            'id' => $itemId,
            'qty_shipped' => 5,
        ]);

        $this->assertDatabaseHas('sales_fulfillment_histories', [
            'sales_order_item_id' => $itemId,
            'delivery_note_id' => $deliveryNote->id,
        ]);

        // 3. Act (Second Run - Duplicate)
        $listener->handle($event);

        $item->refresh();
        $this->assertEquals(5, $item->qty_shipped, "Idempotency Failed: Quantity increased on duplicate event!");
    }

    public function test_logistics_creates_documents_using_event_snapshot()
    {
        $orderId = 'ORDER-UUID-999';

        $itemSnapshot = new OrderItemSnapshotDto(
            productId: 'PROD-001',
            productName: 'Test Product from Snapshot',
            quantity: 50,
            unitPrice: 100.00
        );

        $orderSnapshot = new OrderSnapshotDto(
            orderId: $orderId,
            orderNumber: 'SO-TEST-SNAPSHOT',
            customerId: 'CUST-001',
            companyId: 'COMP-001',
            warehouseId: 'WH-001',
            items: [$itemSnapshot],
            note: 'Deliver via Snapshot'
        );

        $event = new OrderConfirmed($orderId, $orderSnapshot);

        $mockStockRepo = $this->createMock(StockLevelRepositoryInterface::class);
        $mockItemLookup = $this->createMock(ItemLookupServiceInterface::class);
        $mockPickingService = $this->createMock(StockPickingService::class);

        $service = new OrderFulfillmentService(
            $mockStockRepo,
            $mockItemLookup,
            $mockPickingService
        );

        $listener = new CreateLogisticsDocuments($service);

        $listener->handle($event);

        $this->assertDatabaseHas('logistics_picking_slips', [
            'order_id' => $orderId,
            'order_number' => 'SO-TEST-SNAPSHOT',
        ]);

        $this->assertDatabaseHas('logistics_picking_slip_items', [
            'product_name' => 'Test Product from Snapshot',
            'quantity_requested' => 50,
        ]);
    }
}
