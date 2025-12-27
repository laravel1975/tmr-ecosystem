<?php

namespace TmrEcosystem\Logistics\Application\UseCases;

use Illuminate\Support\Facades\DB;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
use TmrEcosystem\Stock\Application\Contracts\StockPickingServiceInterface;

class CreatePickingSlipUseCase
{
    public function __construct(
        // Inject Service สำหรับขอ Strategy การหยิบสินค้า (Inventory BC)
        private StockPickingServiceInterface $stockPickingService
    ) {}

    public function createFromOrder(OrderSnapshotDto $orderSnapshot): PickingSlip
    {
        // 1. Idempotency Check: สร้าง Key จาก Order ID และรายการสินค้า
        // เพื่อป้องกันการสร้างใบหยิบซ้ำหาก Event ถูกส่งมาหลายรอบ
        $idempotencyKey = $this->generateIdempotencyKey($orderSnapshot);

        $existingSlip = PickingSlip::where('idempotency_key', $idempotencyKey)->first();
        if ($existingSlip) {
            return $existingSlip; // Return ใบเดิมทันที (Idempotent)
        }

        return DB::transaction(function () use ($orderSnapshot, $idempotencyKey) {

            // 2. Extract Snapshot Data (Decoupling from Sales/Customer)
            // ดึงที่อยู่จาก Snapshot ที่ Sales ส่งมา (ไม่ใช่จาก DB Customer ปัจจุบัน)
            $shippingAddress = null;
            if (isset($orderSnapshot->customerSnapshot) && is_array($orderSnapshot->customerSnapshot)) {
                $shippingAddress = $orderSnapshot->customerSnapshot['shipping_address'] ?? null;
            }

            // 3. Create Picking Slip Header
            $slip = PickingSlip::create([
                'picking_number' => 'PK-' . $orderSnapshot->orderNumber, // หรือใช้ NumberGenerator
                'order_id' => $orderSnapshot->orderId,
                'order_number' => $orderSnapshot->orderNumber,
                'warehouse_id' => $orderSnapshot->warehouseId,
                'customer_id' => $orderSnapshot->customerId, // เก็บไว้สำหรับการ search แต่อย่าใช้ relation ลึก
                'shipping_address_snapshot' => $shippingAddress, // ✅ Snapshot Address
                'status' => 'pending_allocation',
                'idempotency_key' => $idempotencyKey, // ✅ Key ป้องกันซ้ำ
            ]);

            // 4. Process Items & Request Allocation Strategy
            foreach ($orderSnapshot->items as $itemDto) {

                // ถาม Inventory Context ว่าควรหยิบสินค้านี้จาก Location ไหน (Strategy: FIFO/FEFO)
                // Return: List of [location_id, quantity, batch_id]
                $suggestions = $this->stockPickingService->suggestPickingLocations(
                    productId: $itemDto->productId,
                    quantity: $itemDto->quantity,
                    warehouseId: $orderSnapshot->warehouseId
                );

                // บันทึกรายการที่จะหยิบตามคำแนะนำ (อาจแตกเป็นหลายบรรทัดถ้าหยิบหลาย Location)
                foreach ($suggestions as $suggestion) {
                    PickingSlipItem::create([
                        'picking_slip_id' => $slip->id,
                        'sales_order_item_id' => $itemDto->id, // Reference back to Sales Line
                        'product_id' => $itemDto->productId,
                        'product_name' => $itemDto->productName, // ✅ Snapshot Name
                        'quantity_to_pick' => $suggestion->quantity,
                        'location_id' => $suggestion->locationId, // Location ที่ Inventory แนะนำ
                        'status' => 'pending',
                    ]);
                }

                // *หมายเหตุ: หากไม่มีของใน Stock ($suggestions ว่าง)
                // Logic ส่วนนี้ต้องรองรับการสร้าง Backorder (ข้ามไปก่อน หรือสร้างรายการ status=backorder)
            }

            return $slip;
        });
    }

    private function generateIdempotencyKey(OrderSnapshotDto $dto): string
    {
        // สร้าง Hash จาก OrderId + Item IDs + Quantities
        // หาก Order มีการแก้ไข Item -> Hash เปลี่ยน -> อนุญาตให้สร้างใบหยิบใหม่ (ถูกต้อง)
        $payload = json_encode($dto->items);
        return 'picking_' . $dto->orderId . '_' . md5($payload);
    }
}
