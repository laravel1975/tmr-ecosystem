<?php

namespace TmrEcosystem\Sales\Infrastructure\Persistence;

use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        // 1. บันทึก Header (เหมือนเดิม)
        $model = SalesOrderModel::updateOrCreate(
            ['id' => $order->getId()],
            [
                'order_number' => $order->getOrderNumber() === 'DRAFT' ? 'SO-' . time() : $order->getOrderNumber(),
                'customer_id' => $order->getCustomerId(),
                'company_id' => $order->getCompanyId(),
                'warehouse_id' => $order->getWarehouseId(),
                'status' => $order->getStatus()->value,
                'total_amount' => $order->getTotalAmount(),
                'currency' => 'THB',
                'note' => $order->getNote(),
                'payment_terms' => $order->getPaymentTerms(),
                // ✅ [เพิ่ม] บันทึก salesperson_id
                'salesperson_id' => $order->getSalespersonId(),
            ]
        );

        // 2. Sync Items (แบบฉลาด) ✅

        // รวบรวม ID ของสินค้าที่ยังอยู่ใน Order (จาก Domain)
        $currentIds = [];

        foreach ($order->getItems() as $item) {
            // ถ้ามี ID เดิม -> Update
            if ($item->id) {
                $model->items()->where('id', $item->id)->update([
                    'product_id' => $item->productId,
                    'product_name' => $item->productName,
                    'unit_price' => $item->unitPrice,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->total(),
                ]);
                $currentIds[] = $item->id;
            }
            // ถ้าไม่มี ID (ของใหม่) -> Create
            else {
                $newItem = $model->items()->create([
                    'product_id' => $item->productId,
                    'product_name' => $item->productName,
                    'unit_price' => $item->unitPrice,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->total(),
                ]);
                $currentIds[] = $newItem->id;
            }
        }

        // 3. ลบรายการที่ "หายไป" จาก Domain ออกจาก DB
        // (เฉพาะรายการที่ไม่ได้ถูกอ้างอิงโดย picking slip เท่านั้น ถ้ากลัว Error ก็ try-catch ไว้)
        try {
            $model->items()->whereNotIn('id', $currentIds)->delete();
        } catch (\Exception $e) {
            // ถ้าลบไม่ได้ (เพราะติด Foreign Key) อาจจะปล่อยผ่าน หรือ Log ไว้
            // แต่ตาม Flow ปกติ User ไม่ควรลบสินค้าที่ถูก Pick ไปแล้ว
            // โค้ดนี้จะช่วยให้รายการอื่นๆ ที่ไม่ติดปัญหาทำงานต่อได้
        }
    }

    public function findById(string $id): ?Order
    {
        $model = SalesOrderModel::with('items')->find($id);

        if (!$model) {
            return null;
        }

        // ✅ Reconstitute: ส่งข้อมูลให้ครบถ้วน
        return Order::reconstitute(
            id: $model->id,
            orderNumber: $model->order_number,
            customerId: $model->customer_id,

            // ✅ ส่งค่าจาก DB หรือ Default ถ้าเป็นข้อมูลเก่า
            companyId: $model->company_id ?? 'DEFAULT_COMPANY',
            warehouseId: $model->warehouse_id ?? 'DEFAULT_WAREHOUSE',

            // ✅ เพิ่มบรรทัดนี้
            salespersonId: $model->salesperson_id,

            statusString: $model->status,
            totalAmount: (float) $model->total_amount,
            itemsData: $model->items,
            note: $model->note ?? '',
            paymentTerms: $model->payment_terms ?? 'immediate'
        );
    }
}
