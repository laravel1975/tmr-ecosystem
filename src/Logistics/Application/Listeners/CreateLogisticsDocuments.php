<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Exception;
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
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;

class CreateLogisticsDocuments implements ShouldQueue
{
    use InteractsWithQueue;

    // Config retry settings
    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        // 1. รับ ID มาเท่านั้น เพื่อป้องกัน Stale Data จาก Serialized Model
        $orderId = $event->orderId;

        Log::info("Logistics: Processing Allocation for Order: {$orderId}");

        DB::transaction(function () use ($orderId) {

            // ✅ FIX: Race Condition - ใช้ lockForUpdate()
            $orderModel = SalesOrderModel::where('id', $orderId)->lockForUpdate()->first();

            if (!$orderModel) {
                // กรณีนี้แปลกมาก (Data Consistency ผิดพลาด) ควรแจ้ง Error
                throw new Exception("Logistics Error: Order ID {$orderId} not found in DB processing event.");
            }

            // ถ้า Order นี้ถูก Cancel หรือ Hold ไปแล้วระหว่างรอคิว
            if ($orderModel->status === 'cancelled') {
                Log::info("Logistics: Order {$orderId} was cancelled. Skipping allocation.");
                return;
            }

            $warehouseId = $orderModel->warehouse_id ?? 'DEFAULT_WAREHOUSE';
            $companyId = $orderModel->company_id;

            $itemsToCreate = [];
            $orderFullyFulfilled = true;

            // ดึง items ล่าสุดจาก DB เสมอ
            $orderItems = $orderModel->items;

            foreach ($orderItems as $item) {

                // ✅ FIX: Update Table Name to 'logistics_picking_slip_items'
                $alreadyPickedQty = DB::table('logistics_picking_slip_items')
                    ->join('logistics_picking_slips', 'logistics_picking_slip_items.picking_slip_id', '=', 'logistics_picking_slips.id')
                    ->where('logistics_picking_slips.order_id', $orderId)
                    ->where('logistics_picking_slip_items.product_id', $item->product_id)
                    ->where('logistics_picking_slips.status', '!=', 'cancelled')
                    ->sum('logistics_picking_slip_items.quantity_requested');

                $qtyNeeded = $item->quantity - $alreadyPickedQty;

                if ($qtyNeeded <= 0) {
                    continue; // ครบแล้ว
                }

                $inventoryItem = $this->itemLookupService->findByPartNumber($item->product_id);
                if (!$inventoryItem) {
                    Log::error("Logistics: Product {$item->product_id} not found in Inventory.");
                    $orderFullyFulfilled = false;
                    continue;
                }

                // คำนวณแผนหยิบ
                $plan = $this->pickingService->calculatePickingPlan(
                    $inventoryItem->uuid,
                    $warehouseId,
                    (float) $qtyNeeded
                );

                $qtyAllocatedThisRound = 0;

                foreach ($plan as $step) {
                    $locationUuid = $step['location_uuid'];
                    $qtyToPick = $step['quantity'];

                    if (is_null($locationUuid)) continue;

                    try {
                        $stockLevel = $this->stockRepo->findByLocation(
                            $inventoryItem->uuid, $locationUuid, $companyId
                        );

                        if ($stockLevel) {
                            // ✅ FIX: Pre-check availability เพื่อป้องกันบั๊ก "Half Stock" ใน reserveSoft
                            // ถ้ามีของน้อยกว่าที่ต้องการ ให้ข้ามไปเลย (ถือว่าของขาด)
                            // หรือปรับยอดที่จะหยิบให้เท่าที่มี ($qtyToPick = min($qtyToPick, $stockLevel->getAvailableQuantity()))
                            if ($stockLevel->getAvailableQuantity() < $qtyToPick) {
                                Log::warning("Logistics: Skipping allocation. Stock mismatch at {$locationUuid}. Needed: {$qtyToPick}, Available: " . $stockLevel->getAvailableQuantity());
                                continue;
                            }

                            $stockLevel->reserveSoft($qtyToPick); // ตัดสต็อก
                            $this->stockRepo->save($stockLevel, []);

                            $qtyAllocatedThisRound += $qtyToPick;

                            $itemsToCreate[] = [
                                'product_id' => $item->product_id,
                                'sales_order_item_id' => $item->id,
                                'quantity' => $qtyToPick,
                            ];
                        }
                    } catch (InsufficientStockException $e) {
                        // ✅ FIX: Strict Error Handling
                        // จับ Exception ไว้ เพื่อไม่ให้ Job พัง แต่ Log ไว้ตรวจสอบ
                        Log::warning("Logistics: Insufficient Stock Exception at {$locationUuid}: " . $e->getMessage());
                    } catch (Exception $e) {
                        // System error (DB connection, etc) -> ต้อง Retry
                        throw $e;
                    }
                }

                if ($qtyAllocatedThisRound < $qtyNeeded) {
                    $orderFullyFulfilled = false;
                }
            }

            // สร้าง Picking Slip
            if (count($itemsToCreate) > 0) {
                $pickingSlip = new PickingSlip();
                $pickingSlip->picking_number = 'PK-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                $pickingSlip->company_id = $companyId;
                $pickingSlip->order_id = $orderId;
                $pickingSlip->status = 'pending';
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

                Log::info("Logistics: Created Picking Slip {$pickingSlip->picking_number}");
            }

            // Update Status ที่ Sales Order
            $finalStatus = $orderFullyFulfilled ? 'reserved' : 'backorder';

            // เช็ค Partial
            $hasPreviousSlips = DB::table('logistics_picking_slips')
                ->where('order_id', $orderId)
                ->exists();

            if ($hasPreviousSlips && !$orderFullyFulfilled) {
                 $finalStatus = 'partial_reserved';
            }

            $orderModel->update(['stock_status' => $finalStatus]);
        });
    }

    private function createDeliveryNote($orderModel, $pickingSlip)
    {
        $dn = new DeliveryNote();
        $dn->delivery_number = 'DO-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $dn->company_id = $orderModel->company_id;
        $dn->order_id = $orderModel->id;
        $dn->picking_slip_id = $pickingSlip->id;
        $dn->status = 'waiting_picking';

        // Snapshot Data (สำคัญมาก)
        $dn->shipping_address = $orderModel->shipping_address ?? 'Address N/A';
        $dn->contact_person = $orderModel->contact_person ?? 'N/A';

        $dn->save();
    }
}
