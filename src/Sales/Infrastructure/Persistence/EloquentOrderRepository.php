<?php

namespace TmrEcosystem\Sales\Infrastructure\Persistence;

use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        // 1. บันทึก Header
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
                'salesperson_id' => $order->getSalespersonId(),
            ]
        );

        // 2. Sync Items
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

        // 3. ลบรายการที่ "หายไป" ออก
        try {
            $model->items()->whereNotIn('id', $currentIds)->delete();
        } catch (\Exception $e) {
            // Ignore fk constraint error just in case
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
            companyId: $model->company_id ?? 'DEFAULT_COMPANY',
            warehouseId: $model->warehouse_id ?? 'DEFAULT_WAREHOUSE',
            salespersonId: $model->salesperson_id,
            statusString: $model->status,
            totalAmount: (float) $model->total_amount,

            // ✅ [FIXED] ต้องแปลง Collection เป็น Array ก่อนส่งให้ Domain Entity
            itemsData: $model->items->toArray(),

            note: $model->note ?? '',
            paymentTerms: $model->payment_terms ?? 'immediate'
        );
    }
}
