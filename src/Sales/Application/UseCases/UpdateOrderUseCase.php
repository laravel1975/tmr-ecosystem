<?php

namespace TmrEcosystem\Sales\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Sales\Application\DTOs\UpdateOrderDto;
use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Services\ProductCatalogInterface;
use TmrEcosystem\Communication\Infrastructure\Persistence\Models\CommunicationMessage;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated;

class UpdateOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog
    ) {}

    public function handle(string $orderId, UpdateOrderDto $dto): Order
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) throw new Exception("Order not found");

        if (in_array($order->getStatus(), [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot update finalized orders.");
        }

        $oldTotal = $order->getTotalAmount();
        // เก็บสถานะเดิมไว้เช็ค
        $wasConfirmed = $order->getStatus() === OrderStatus::Confirmed;

        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

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
                // ✅ FIX: ส่ง $orderId (String) แทน Object $order
                OrderConfirmed::dispatch($orderId);
            }
            // 2. ถ้า Confirmed อยู่แล้วและมีการแก้ไข -> ยิง OrderUpdated
            elseif ($wasConfirmed) {
                // หมายเหตุ: ตรวจสอบ Event OrderUpdated ของคุณด้วยว่ารับค่าแบบไหน
                // ถ้าเป็นไปได้ควรแก้ให้รับ $orderId เหมือนกันเพื่อความ Consistency
                // แต่ถ้า Event นั้นยังรับ Model อยู่ บรรทัดนี้ก็ใช้ได้ครับ
                OrderUpdated::dispatch($order);
            }

            return $order;
        });
    }
}
