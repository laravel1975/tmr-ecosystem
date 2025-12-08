<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
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
        Log::info("Logistics: Processing 1:N Allocation for Order: {$orderAggregate->getOrderNumber()}");

        $orderModel = SalesOrderModel::where('id', $orderAggregate->getId())->first();
        if (!$orderModel) return;

        DB::transaction(function () use ($orderModel, $orderAggregate) {

            $warehouseId = $orderModel->warehouse_id ?? 'DEFAULT_WAREHOUSE';
            $companyId = $orderModel->company_id;
            $generalLocationUuid = $this->ensureGeneralLocation($warehouseId);

            $itemsToCreate = [];
            $orderFullyFulfilled = true;

            // 1. วนลูปสินค้าทุกรายการใน Order
            foreach ($orderAggregate->getItems() as $item) {

                // 1.1 คำนวณยอดที่ต้องจัด (Pending Qty)
                // สูตร: ยอดสั่ง - ยอดที่เคยออกใบหยิบไปแล้ว
                $alreadyPickedQty = DB::table('sales_picking_slip_items')
                    ->join('sales_picking_slips', 'sales_picking_slip_items.picking_slip_id', '=', 'sales_picking_slips.id')
                    ->where('sales_picking_slips.order_id', $orderModel->id) // ของออเดอร์นี้
                    ->where('sales_picking_slip_items.product_id', $item->productId)
                    ->where('sales_picking_slips.status', '!=', 'cancelled') // ไม่นับใบที่ยกเลิก
                    ->sum('sales_picking_slip_items.quantity_requested');

                $qtyNeeded = $item->quantity - $alreadyPickedQty;

                // ถ้าหยิบครบแล้ว ข้ามไปสินค้าตัวถัดไป
                if ($qtyNeeded <= 0) {
                    Log::info("Logistics: Item {$item->productName} already fully picked.");
                    continue;
                }

                // 1.2 เช็คสต็อกและวางแผนการหยิบ (เฉพาะยอดที่ขาด)
                $inventoryItem = $this->itemLookupService->findByPartNumber($item->productId);
                if (!$inventoryItem) continue;

                $plan = $this->pickingService->calculatePickingPlan(
                    $inventoryItem->uuid,
                    $warehouseId,
                    (float) $qtyNeeded
                );

                $qtyAllocatedThisRound = 0;

                // 1.3 จองของตามแผน (Reserve Stock)
                foreach ($plan as $step) {
                    $locationUuid = $step['location_uuid'];
                    $qtyToPick = $step['quantity'];

                    if (is_null($locationUuid)) continue; // ไม่มีของในคลัง ข้าม

                    try {
                        $stockLevel = $this->stockRepo->findByLocation(
                            $inventoryItem->uuid, $locationUuid, $companyId
                        );

                        if ($stockLevel) {
                            $stockLevel->reserveSoft($qtyToPick); // ✅ ตัดสต็อกจริงที่นี่
                            $this->stockRepo->save($stockLevel, []);

                            $qtyAllocatedThisRound += $qtyToPick;

                            // เก็บข้อมูลไว้เตรียมสร้าง Picking Slip Item
                            $itemsToCreate[] = [
                                'product_id' => $item->productId,
                                'sales_order_item_id' => $this->findSalesOrderItemId($orderModel->id, $item->productId),
                                'quantity' => $qtyToPick,
                            ];
                        }
                    } catch (InsufficientStockException $e) {
                        Log::warning("Logistics: Reservation failed at {$locationUuid}");
                    }
                }

                // เช็คว่ารอบนี้หยิบครบตามที่ขาดไหม
                if ($qtyAllocatedThisRound < $qtyNeeded) {
                    $orderFullyFulfilled = false; // ยังมีของขาด (Backorder)
                }
            }

            // 2. สร้าง Picking Slip (เฉพาะถ้ามียอดให้หยิบในรอบนี้)
            if (count($itemsToCreate) > 0) {

                $pickingSlip = new PickingSlip();
                $pickingSlip->picking_number = 'PK-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                $pickingSlip->company_id = $companyId;
                $pickingSlip->order_id = $orderModel->id;

                // ถ้า Picking Slip นี้ได้ของไม่ครบตามที่ขอในรอบนี้ ก็ถือเป็น Partial ของรอบนี้
                // แต่ในมุมมอง 1:N เราถือว่าใบนี้ "Ready" ให้คนไปหยิบได้เลย
                $pickingSlip->status = 'ready';

                $pickingSlip->save();

                foreach ($itemsToCreate as $data) {
                    $line = new PickingSlipItem();
                    $line->picking_slip_id = $pickingSlip->id;
                    $line->sales_order_item_id = $data['sales_order_item_id'];
                    $line->product_id = $data['product_id'];
                    $line->quantity_requested = $data['quantity'];
                    $line->quantity_picked = 0;
                    $line->save();
                }

                $this->createDeliveryNote($orderModel, $pickingSlip);

                Log::info("Logistics: Created Picking Slip {$pickingSlip->picking_number} (Partial/Full Allocation)");
            } else {
                Log::info("Logistics: No stock available for remaining items. Waiting for replenishment.");
            }

            // 3. อัปเดตสถานะ Order หลัก
            // ถ้าของครบหมดแล้ว -> Reserved
            // ถ้ายังขาดอยู่ -> Backorder
            $finalStatus = $orderFullyFulfilled ? 'reserved' : 'backorder';

            // เช็คว่าเคยมี Picking Slip มาก่อนไหม ถ้ามีแล้วยัง Backorder แสดงว่าเป็น Partial Delivery
            if ($orderModel->pickingSlips()->count() > 0 && !$orderFullyFulfilled) {
                 $finalStatus = 'backorder'; // หรือ 'partial' แล้วแต่ Business Definition
            }

            $orderModel->update(['stock_status' => $finalStatus]);
        });
    }

    // ... (Helper Functions: findSalesOrderItemId, createDeliveryNote, ensureGeneralLocation คงเดิม) ...
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
