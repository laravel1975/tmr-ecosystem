<?php

namespace TmrEcosystem\Sales\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Communication\Infrastructure\Persistence\Models\CommunicationMessage;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Sales\Domain\Events\OrderCancelled;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
// ✅ Import Interface
use TmrEcosystem\Sales\Application\Contracts\StockReservationInterface;

class CancelOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private StockReservationInterface $stockReservation // ✅ Inject Service
    ) {}

    public function handle(string $orderId): void
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) throw new Exception("Order not found");

        if ($order->getStatus() === OrderStatus::Cancelled) {
            throw new Exception("Order is already cancelled.");
        }

        // Validation: ห้ามยกเลิกถ้าส่งของไปแล้ว (Logistics Check)
        $delivery = DeliveryNote::where('order_id', $orderId)->first();
        if ($delivery && in_array($delivery->status, ['shipped', 'delivered'])) {
            throw new Exception("ไม่สามารถยกเลิกออเดอร์ได้ เนื่องจากสินค้าถูกจัดส่งแล้ว (กรุณาทำใบคืนสินค้าแทน)");
        }

        DB::transaction(function () use ($order, $orderId) {
            // ✅ 1. เตรียมข้อมูลสินค้าเพื่อคืน Stock (Release Reservation)
            // ทำก่อน save status เพื่อให้แน่ใจว่าถ้า error จะ rollback ทั้งหมด
            if ($order->getStatus() !== OrderStatus::Draft) {
                // ดึงรายการสินค้าทั้งหมดใน Order
                $itemsToRelease = $order->getItems()->map(fn($item) => [
                    'product_id' => $item->productId,
                    'quantity' => $item->quantity
                ])->toArray();

                // เรียก Service เพื่อปลดล็อค Stock
                $this->stockReservation->releaseReservation(
                    orderId: $order->getId(),
                    items: $itemsToRelease,
                    warehouseId: $order->getWarehouseId()
                );
            }

            // 2. เรียก Domain Logic เปลี่ยนสถานะ
            $order->cancel();

            // 3. บันทึกสถานะใหม่ลง DB
            $this->orderRepository->save($order);

            // 4. บันทึก Log ลง Chatter
            CommunicationMessage::create([
                'user_id' => auth()->id(),
                'body' => "Order has been CANCELLED (ยกเลิกใบสั่งขาย) และคืนการจองสินค้าแล้ว",
                'type' => 'notification',
                'model_type' => 'sales_order',
                'model_id' => $orderId
            ]);

            // 5. Trigger Event
            OrderCancelled::dispatch($order);

            Log::info("Sales BC: Order {$order->getOrderNumber()} cancelled and stock released.");
        });
    }
}
