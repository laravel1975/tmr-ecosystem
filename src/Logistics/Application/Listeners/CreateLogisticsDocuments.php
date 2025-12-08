<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
// Services
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Services\StockPickingService;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Domain\Aggregates\StockLevel;
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;

class CreateLogisticsDocuments implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $orderAggregate = $event->order;
        Log::info("Logistics: Processing Smart Allocation for Order: {$orderAggregate->getOrderNumber()}");

        $orderModel = SalesOrderModel::where('id', $orderAggregate->getId())->first();
        if (!$orderModel) {
            Log::error("Logistics: Order not found in DB for ID {$orderAggregate->getId()}");
            return;
        }

        DB::transaction(function () use ($orderModel, $orderAggregate) {

            $warehouseId = $orderModel->warehouse_id ?? 'DEFAULT_WAREHOUSE'; // ควรดึง Warehouse จริง
            $companyId = $orderModel->company_id;

            // 1. เตรียม General Location (Fallback)
            $generalLocationUuid = $this->ensureGeneralLocation($warehouseId);

            $pickingItems = [];
            $hasBackorder = false;

            // 2. Loop สินค้าและคำนวณ Picking Plan (Location-based)
            foreach ($orderAggregate->getItems() as $item) {

                $inventoryItem = $this->itemLookupService->findByPartNumber($item->productId);
                if (!$inventoryItem) {
                    Log::warning("Logistics: Item {$item->productName} not found in Inventory.");
                    continue;
                }

                // คำนวณแผนการหยิบ (Smart Picking Strategy)
                $plan = $this->pickingService->calculatePickingPlan(
                    $inventoryItem->uuid,
                    $warehouseId,
                    (float) $item->quantity
                );

                $totalAllocatedForItem = 0;

                foreach ($plan as $step) {
                    $locationUuid = $step['location_uuid'];
                    $qtyToReserve = $step['quantity'];

                    // Fallback to GENERAL if no location found
                    if (is_null($locationUuid)) {
                        $locationUuid = $generalLocationUuid;
                    }

                    // --- STOCK RESERVATION LOGIC ---
                    $stockLevel = $this->stockRepo->findByLocation(
                        $inventoryItem->uuid,
                        $locationUuid,
                        $companyId
                    );

                    if (!$stockLevel) {
                        $stockLevel = StockLevel::create(
                            uuid: $this->stockRepo->nextUuid(),
                            companyId: $companyId,
                            itemUuid: $inventoryItem->uuid,
                            warehouseUuid: $warehouseId,
                            locationUuid: $locationUuid
                        );
                        $this->stockRepo->save($stockLevel, []);
                    }

                    try {
                        // ทำการจองจริง (Soft Reserve)
                        $stockLevel->reserveSoft($qtyToReserve);
                        $this->stockRepo->save($stockLevel, []);

                        $totalAllocatedForItem += $qtyToReserve;

                        // เก็บข้อมูลเพื่อสร้าง Picking Slip Item
                        $pickingItems[] = [
                            'product_id' => $item->productId,
                            'sales_order_item_id' => $this->findSalesOrderItemId($orderModel->id, $item->productId),
                            'location_id' => $locationUuid, // ✅ เก็บ Location ที่ต้องไปหยิบ
                            'quantity' => $qtyToReserve
                        ];

                    } catch (InsufficientStockException $e) {
                        Log::warning("Logistics: Insufficient stock at location {$locationUuid}. Partial/Backorder triggered.");
                    }
                }

                if ($totalAllocatedForItem < $item->quantity) {
                    $hasBackorder = true;
                }
            }

            // 3. Update Order Status
            if ($hasBackorder) {
                $orderModel->update(['stock_status' => 'backorder']);
                Log::info("Logistics: Order marked as BACKORDER.");
            } else {
                $orderModel->update(['stock_status' => 'reserved']);
            }

            // 4. สร้าง Picking Slip (ถ้ามีการจองของได้)
            if (count($pickingItems) > 0) {
                $pickingSlip = new PickingSlip();
                $pickingSlip->picking_number = 'PK-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                $pickingSlip->company_id = $companyId;
                $pickingSlip->order_id = $orderModel->id;
                $pickingSlip->status = $hasBackorder ? 'partial' : 'ready';
                $pickingSlip->save();

                foreach ($pickingItems as $data) {
                    $line = new PickingSlipItem();
                    $line->picking_slip_id = $pickingSlip->id;
                    $line->sales_order_item_id = $data['sales_order_item_id'];
                    $line->product_id = $data['product_id'];
                    $line->quantity_requested = $data['quantity'];
                    $line->quantity_picked = 0;
                    // $line->location_id = $data['location_id']; // ⚠️ ถ้าตาราง PickingSlipItem มี field นี้จะดีมาก
                    $line->save();
                }

                // 5. สร้าง Delivery Note รอไว้
                $this->createDeliveryNote($orderModel, $pickingSlip);

                Log::info("Logistics: Created Picking Slip {$pickingSlip->picking_number}");
            } else {
                Log::warning("Logistics: No items allocated. No Picking Slip created.");
            }
        });
    }

    private function findSalesOrderItemId($orderId, $productId)
    {
        return DB::table('sales_order_items')
            ->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->value('id');
    }

    private function createDeliveryNote($orderModel, $pickingSlip)
    {
        $dn = new DeliveryNote();
        $dn->delivery_number = 'DO-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        $dn->company_id = $orderModel->company_id;
        $dn->order_id = $orderModel->id;
        $dn->picking_slip_id = $pickingSlip->id;
        $dn->status = 'waiting_picking';
        $dn->shipping_address = 'See Order';
        $dn->contact_person = 'See Order';
        $dn->save();
    }

    private function ensureGeneralLocation($warehouseUuid)
    {
        $uuid = DB::table('warehouse_storage_locations')
            ->where('warehouse_uuid', $warehouseUuid)
            ->where('code', 'GENERAL')
            ->value('uuid');

        if (!$uuid) {
            $uuid = Str::uuid()->toString();
            DB::table('warehouse_storage_locations')->insert([
                'uuid' => $uuid,
                'warehouse_uuid' => $warehouseUuid,
                'code' => 'GENERAL',
                'type' => 'BULK',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $uuid;
    }
}
