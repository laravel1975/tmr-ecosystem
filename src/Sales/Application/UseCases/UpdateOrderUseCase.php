<?php

namespace TmrEcosystem\Sales\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // เพิ่ม Log
use TmrEcosystem\Sales\Application\DTOs\UpdateOrderDto;
use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Services\ProductCatalogInterface;
use TmrEcosystem\Communication\Infrastructure\Persistence\Models\CommunicationMessage;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated;
use TmrEcosystem\Sales\Domain\Services\CreditCheckService; // ✅ Inject Service
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip; // ✅ Import PickingSlip

class UpdateOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog,
        private CreditCheckService $creditCheckService // ✅ Inject Credit Check
    ) {}

    public function handle(string $orderId, UpdateOrderDto $dto): Order
    {
        Log::info("UpdateOrderUseCase: Starting update for Order ID {$orderId}");

        $order = $this->orderRepository->findById($orderId);
        if (!$order) throw new Exception("Order not found");

        if (in_array($order->getStatus(), [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot update finalized orders.");
        }

        // ------------------------------------------------------------------
        // ✅ [GUARD 1] Logistics Status Check
        // ห้ามแก้ไข ถ้า Logistics เริ่มทำงานแล้ว (ป้องกันข้อมูลไม่ตรงหน้างาน)
        // ต้องให้ Logistics ยกเลิก Picking Slip ก่อนถึงจะกลับมาแก้ได้
        // ------------------------------------------------------------------
        $pickingSlip = PickingSlip::where('order_id', $order->id)->first();
        if ($pickingSlip && in_array($pickingSlip->status, ['in_progress', 'done', 'packed', 'shipped'])) {
            throw new Exception("Cannot update order: Picking process has already started or finished. Please contact warehouse to cancel the picking slip first.");
        }

        $oldTotal = $order->getTotalAmount();
        // เก็บสถานะเดิมไว้เช็ค
        $wasConfirmed = $order->getStatus() === OrderStatus::Confirmed;

        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

        // ------------------------------------------------------------------
        // ✅ [GUARD 2] Pre-Calculate for Financial Control Check
        // คำนวณยอดใหม่คร่าวๆ เพื่อเช็คเครดิตก่อนเริ่ม Transaction
        // ------------------------------------------------------------------
        $newGrandTotal = 0;
        foreach ($dto->items as $itemDto) {
             if ($itemDto->quantity <= 0) continue;
             $product = $products[$itemDto->productId] ?? null;
             if ($product) {
                 $newGrandTotal += $product->price * $itemDto->quantity;
             }
        }

        // ถ้ายอดใหม่ สูงกว่า ยอดเดิม ให้เช็ควงเงิน
        // (และต้องเป็นออร์เดอร์ที่ Confirm แล้ว หรือกำลังจะ Confirm)
        if (($wasConfirmed || $dto->confirmOrder) && $newGrandTotal > $oldTotal) {
            $increaseAmount = $newGrandTotal - $oldTotal;
            // เมธอดนี้จะ Throw Exception ถ้าวงเงินไม่พอ
            $this->creditCheckService->canPlaceOrder($order->getCustomerId(), $increaseAmount);
        }

        return DB::transaction(function () use ($order, $dto, $products, $oldTotal, $orderId, $wasConfirmed) {

            $order->updateDetails($dto->customerId, $dto->note, $dto->paymentTerms);
            $order->clearItems(); // เคลียร์ List ใน Memory

            foreach ($dto->items as $itemDto) {
                if ($itemDto->quantity <= 0) continue;

                $product = $products[$itemDto->productId] ?? null;
                if (!$product) continue;

                $order->addItem(
                    productId: $product->id,
                    productName: $product->name,
                    price: $product->price,
                    quantity: $itemDto->quantity,
                    id: $itemDto->id
                );
            }

            // Logic Confirm เดิม
            $isJustConfirmed = false;
            if ($dto->confirmOrder && $order->getStatus() === OrderStatus::Draft) {
                $order->confirm();
                $isJustConfirmed = true;
            }

            $this->orderRepository->save($order);

            // Logic Log ราคา
            $newTotal = $order->getTotalAmount();
            if ($order->getStatus() === OrderStatus::Confirmed && $oldTotal != $newTotal) {
                $diff = $newTotal - $oldTotal;
                $sign = $diff > 0 ? '+' : '';
                $logMessage = sprintf(
                    "มีการแก้ไขรายการสินค้า: ยอดรวมเปลี่ยนจาก %s ฿ เป็น %s ฿ (ส่วนต่าง: %s%s ฿)",
                    number_format($oldTotal, 2), number_format($newTotal, 2), $sign, number_format($diff, 2)
                );
                CommunicationMessage::create([
                    'user_id' => auth()->id(),
                    'body' => $logMessage,
                    'type' => 'notification',
                    'model_type' => 'sales_order',
                    'model_id' => $orderId
                ]);
            }

            // --- Event Dispatching ---

            // 1. ถ้าเพิ่ง Confirm -> ยิง OrderConfirmed
            if ($isJustConfirmed) {
                // ส่ง $orderId (String) ตามที่ Event ต้องการ
                OrderConfirmed::dispatch($orderId);
            }
            // 2. ถ้า Confirmed อยู่แล้วและมีการแก้ไข -> ยิง OrderUpdated เพื่อ Sync Logistics
            elseif ($wasConfirmed) {
                 // ส่ง Object $order (เพราะ SyncLogisticsDocuments.php ใช้ $event->orderId->id หรือ $event->orderId)
                 // เพื่อความปลอดภัย แนะนำให้แก้ Event OrderUpdated ให้รับ ID อย่างเดียวเหมือนกันในอนาคต
                 // แต่ ณ ตอนนี้ ส่ง $order ไปก่อนเพื่อให้ Listener ทำงานต่อได้
                OrderUpdated::dispatch($order);
            }

            return $order;
        });
    }
}
