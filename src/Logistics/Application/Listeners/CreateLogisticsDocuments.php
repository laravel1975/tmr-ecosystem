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

    // ตั้งค่า Retry ถ้าเกิด Deadlock หรือ Database connection หลุด
    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        // รองรับทั้งแบบส่ง ID มา หรือส่ง Object มา (เผื่อ Legacy code)
        $orderId = is_string($event->orderId) ? $event->orderId : $event->orderId->id ?? null;

        if (!$orderId) {
            Log::error("Logistics: Invalid Event payload. Order ID missing.");
            return;
        }

        Log::info("Logistics: Processing Allocation for Order: {$orderId}");

        DB::transaction(function () use ($orderId) {

            // ✅ 1. Lock Row ของ Order เพื่อป้องกันการทำงานซ้ำซ้อน (Concurrency Control)
            $orderModel = SalesOrderModel::where('id', $orderId)->lockForUpdate()->first();

            if (!$orderModel) {
                Log::error("Logistics: Order ID {$orderId} not found in DB.");
                return; // ออกจาก Job เงียบๆ (หรือจะ Throw ก็ได้แล้วแต่ Policy)
            }

            if ($orderModel->status === 'cancelled') {
                Log::info("Logistics: Order {$orderId} was cancelled. Skipping.");
                return;
            }

            $warehouseId = $orderModel->warehouse_id ?? 'DEFAULT_WAREHOUSE';
            $companyId = $orderModel->company_id;

            $itemsToCreate = []; // รายการที่จะบันทึกลง Picking Slip
            $orderFullyFulfilled = true; // Flag เช็คว่า Order นี้ได้ของครบไหม

            // โหลด Items ล่าสุดจาก DB เสมอ
            $orderItems = $orderModel->items;

            foreach ($orderItems as $item) {

                // 2. คำนวณยอดที่ยังขาด (Qty Needed)
                // หักยอดที่เคยออก Picking Slip ไปแล้ว (กรณี Backorder เก่า)
                $alreadyPickedQty = DB::table('logistics_picking_slip_items')
                    ->join('logistics_picking_slips', 'logistics_picking_slip_items.picking_slip_id', '=', 'logistics_picking_slips.id')
                    ->where('logistics_picking_slips.order_id', $orderId)
                    ->where('logistics_picking_slip_items.product_id', $item->product_id)
                    ->where('logistics_picking_slips.status', '!=', 'cancelled')
                    ->sum('logistics_picking_slip_items.quantity_requested');

                $qtyNeeded = $item->quantity - $alreadyPickedQty;

                if ($qtyNeeded <= 0) continue; // ครบแล้ว ข้ามไปสินค้าตัวถัดไป

                // ค้นหาข้อมูลสินค้า (Inventory)
                $inventoryItem = $this->itemLookupService->findByPartNumber($item->product_id);
                if (!$inventoryItem) {
                    Log::error("Logistics: Product {$item->product_id} not found in Inventory.");
                    $orderFullyFulfilled = false;
                    continue;
                }

                // 3. วางแผนการหยิบ (Picking Strategy) ว่าจะหยิบจาก Location ไหนบ้าง
                $plan = $this->pickingService->calculatePickingPlan(
                    $inventoryItem->uuid,
                    $warehouseId,
                    (float) $qtyNeeded
                );

                $qtyAllocatedThisRound = 0; // ยอดที่จองได้จริงในรอบนี้

                foreach ($plan as $step) {
                    $locationUuid = $step['location_uuid'];
                    $qtyToPick = $step['quantity'];

                    if (is_null($locationUuid)) continue;

                    try {
                        // ดึง Stock Level ของ Location นั้นๆ
                        $stockLevel = $this->stockRepo->findByLocation(
                            $inventoryItem->uuid, $locationUuid, $companyId
                        );

                        if ($stockLevel) {
                            // ✅ 4. Pre-Check Availability (ป้องกัน Error เบื้องต้น)
                            // เช็คยอดคงเหลือจริงก่อนเรียก reserveSoft
                            if ($stockLevel->getAvailableQuantity() < $qtyToPick) {
                                Log::warning("Logistics: Stock mismatch at {$locationUuid}. Needed: {$qtyToPick}, Available: " . $stockLevel->getAvailableQuantity());
                                // ทางเลือก: ข้ามไปเลย หรือ จองเท่าที่มี
                                // ในที่นี้ขอเลือก "ข้าม" เพื่อความปลอดภัยของข้อมูล
                                continue;
                            }

                            // ✅ 5. Action: สั่งจอง (Domain Logic จะเช็คซ้ำอีกทีเพื่อความชัวร์)
                            $stockLevel->reserveSoft($qtyToPick);

                            // บันทึกสถานะ Stock ล่าสุด
                            $this->stockRepo->save($stockLevel, []);

                            // เก็บข้อมูลเพื่อสร้าง Picking Slip Item
                            $qtyAllocatedThisRound += $qtyToPick;
                            $itemsToCreate[] = [
                                'product_id' => $item->product_id,
                                'sales_order_item_id' => $item->id,
                                'quantity' => $qtyToPick,
                            ];
                        }
                    } catch (InsufficientStockException $e) {
                        // ดักจับ Business Error (ของไม่พอ) -> Log ไว้ แต่ไม่ให้ Job พัง
                        Log::warning("Logistics: Insufficient Stock at {$locationUuid}: " . $e->getMessage());
                    } catch (Exception $e) {
                        // System Error (DB หลุด, etc) -> Throw เพื่อให้ Job Retry
                        throw $e;
                    }
                }

                // ถ้าจองได้น้อยกว่าที่ขอ แสดงว่าของไม่พอ (Backorder)
                if ($qtyAllocatedThisRound < $qtyNeeded) {
                    $orderFullyFulfilled = false;
                }
            }

            // 6. สร้างเอกสาร Picking Slip (ถ้ามียอดที่จองได้)
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

                // สร้าง Delivery Note ล่วงหน้า (สถานะ Waiting Picking)
                $this->createDeliveryNote($orderModel, $pickingSlip);

                Log::info("Logistics: Created Picking Slip {$pickingSlip->picking_number}");
            }

            // 7. อัปเดตสถานะที่ Sales Order
            $finalStatus = $orderFullyFulfilled ? 'reserved' : 'backorder';

            // ตรวจสอบว่าเคยมีการจองมาก่อนหน้านี้ไหม (Partial Reserved)
            $hasPreviousSlips = DB::table('logistics_picking_slips')
                ->where('order_id', $orderId)
                ->exists();

            if ($hasPreviousSlips && !$orderFullyFulfilled) {
                 $finalStatus = 'partial_reserved';
            }

            // อัปเดตสถานะ Stock ของ Order
            // หมายเหตุ: column 'stock_status' ต้องมีใน sales_orders table (ถ้าไม่มีต้องเพิ่ม Migration)
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

        // Snapshot Data: สำเนาที่อยู่ ณ ตอนสร้างเอกสาร
        $dn->shipping_address = $orderModel->shipping_address ?? 'Address N/A';
        $dn->contact_person = $orderModel->contact_person ?? 'N/A';

        $dn->save();
    }
}
