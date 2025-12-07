<?php

namespace TmrEcosystem\Stock\Application\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Domain\Aggregates\StockLevel;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Services\StockPickingService;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class ReserveStockOnOrderConfirmed
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;
        $items = $order->getItems();
        $warehouseUuid = $order->getWarehouseId();
        $companyId = $order->getCompanyId();

        Log::info("Stock: Processing Smart Reserve for Order: {$order->getOrderNumber()}");

        DB::transaction(function () use ($order, $items, $warehouseUuid, $companyId) {

            // 1. เตรียม Cache ของ GENERAL UUID
            $generalLocationUuid = DB::table('warehouse_storage_locations')
                ->where('warehouse_uuid', $warehouseUuid)
                ->where('code', 'GENERAL')
                ->value('uuid');

            if (!$generalLocationUuid) {
                $generalLocationUuid = \Illuminate\Support\Str::uuid()->toString();
                DB::table('warehouse_storage_locations')->insert([
                    'uuid' => $generalLocationUuid,
                    'warehouse_uuid' => $warehouseUuid,
                    'code' => 'GENERAL',
                    'barcode' => 'GENERAL-' . substr($warehouseUuid, 0, 4),
                    'type' => 'BULK',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $hasBackorder = false;

            foreach ($items as $item) {
                $productId = $item->productId;
                $qtyNeeded = (float) $item->quantity;

                $inventoryItemDto = $this->itemLookupService->findByPartNumber($productId);
                if (!$inventoryItemDto) continue;

                $plan = $this->pickingService->calculatePickingPlan(
                    $inventoryItemDto->uuid,
                    $warehouseUuid,
                    $qtyNeeded
                );

                foreach ($plan as $step) {
                    $locationUuid = $step['location_uuid'];
                    $qtyToReserve = $step['quantity'];

                    if (is_null($locationUuid)) {
                        $locationUuid = $generalLocationUuid;
                        Log::info("Stock: Backorder fallback to GENERAL for {$productId}");
                    }

                    $stockLevel = $this->stockRepo->findByLocation(
                        $inventoryItemDto->uuid,
                        $locationUuid,
                        $companyId
                    );

                    if (!$stockLevel) {
                        $stockLevel = StockLevel::create(
                            uuid: $this->stockRepo->nextUuid(),
                            companyId: $companyId,
                            itemUuid: $inventoryItemDto->uuid,
                            warehouseUuid: $warehouseUuid,
                            locationUuid: $locationUuid
                        );
                        $this->stockRepo->save($stockLevel, []);
                    }

                    try {
                        $stockLevel->reserveSoft($qtyToReserve);
                        $this->stockRepo->save($stockLevel, []);
                        Log::info("Stock: Reserved {$qtyToReserve} at location {$locationUuid}");

                    } catch (InsufficientStockException $e) {
                        Log::warning("Stock: Insufficient stock for Order {$order->getOrderNumber()} Item {$productId}. Marking as BACKORDER.");
                        $hasBackorder = true;
                    }
                }
            }

            // 6. อัปเดตสถานะที่ SalesOrderModel (Database)
            // ✅ FIX: เปลี่ยนจาก where('uuid', ...) เป็น where('id', ...)
            $orderModel = SalesOrderModel::where('id', $order->getId())->first();

            if ($orderModel) {
                if ($hasBackorder) {
                    if (Schema::hasColumn('sales_orders', 'stock_status')) {
                        $orderModel->update(['stock_status' => 'backorder']);
                    }
                } else {
                    if (Schema::hasColumn('sales_orders', 'stock_status')) {
                        $orderModel->update(['stock_status' => 'reserved']);
                    }
                }
            }
        });
    }
}
