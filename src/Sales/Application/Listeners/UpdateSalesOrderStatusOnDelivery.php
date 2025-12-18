<?php

namespace TmrEcosystem\Sales\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Logistics\Domain\Events\DeliveryNoteUpdated;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
// ใช้ Service ของ Logistics เพื่อดึงยอดรวมส่งจริง (ถ้าจำเป็น)
// หรือสมมติว่า Event ส่งข้อมูล items มาให้ครบ
use TmrEcosystem\Sales\Domain\Aggregates\Order;

class UpdateSalesOrderStatusOnDelivery implements ShouldQueue
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository
    ) {}

    public function handle(DeliveryNoteUpdated $event): void
    {
        $deliveryNoteId = $event->deliveryNoteId;

        // สมมติว่าเราดึงข้อมูล DeliveryNote และรายการสินค้ามาได้
        // ในความเป็นจริงอาจต้อง Inject Service เพื่อดึงข้อมูล หรือ Event ควรมีข้อมูลนี้มาให้
        // $deliveryNote = ...;
        // $orderId = $deliveryNote->order_id;

        // ตัวอย่าง Logic จำลอง (Mockup) ว่าเราได้ OrderID และ ItemShippedUpdate มาแล้ว
        // $orderId = ...
        // $shippedUpdates = [ 'order_item_id_1' => 5, 'order_item_id_2' => 10 ];

        Log::info("UpdateSalesOrderStatus: Processing delivery update for Note ID {$deliveryNoteId}");

        // ⚠️ หมายเหตุ: Code ส่วนนี้ต้องปรับให้เข้ากับ Event จริงของระบบคุณ
        // สมมติว่า Event มี method getOrderUpdates() ที่ return array ['order_id' => '...', 'items' => ['item_id' => qty]]
        // หรือถ้าไม่มี ต้อง query จาก Logistics Module ผ่าน Interface

        // --- Implementation Logic ---

        // 1. Load Aggregate Root
        // $order = $this->orderRepository->findById($orderId);
        // if (!$order) return;

        // 2. Loop update items via Domain Method
        // foreach ($shippedUpdates as $itemId => $qtyShippedTotal) {
        //     $order->updateItemShipmentStatus($itemId, $qtyShippedTotal);
        // }

        // 3. Save Aggregate (Trigger internal status recalculation)
        // $this->orderRepository->save($order);

        Log::info("UpdateSalesOrderStatus: Order status updated successfully.");
    }
}
