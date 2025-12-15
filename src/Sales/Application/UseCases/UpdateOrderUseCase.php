<?php

namespace TmrEcosystem\Sales\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Application\DTOs\UpdateOrderDto;
use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Services\ProductCatalogInterface;
use TmrEcosystem\Communication\Infrastructure\Persistence\Models\CommunicationMessage;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated;
// ✅ Import เพิ่มเติม
use TmrEcosystem\Sales\Domain\Services\CreditCheckService;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;

class UpdateOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog,
        private CreditCheckService $creditCheckService // ✅ Inject Service
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
        // แก้ไข: ใช้ $order->getId() แทน $order->id
        // ------------------------------------------------------------------
        $pickingSlip = PickingSlip::where('order_id', $order->getId())->first();

        if ($pickingSlip && in_array($pickingSlip->status, ['in_progress', 'done', 'packed', 'shipped'])) {
            throw new Exception("Cannot update order: Picking process has already started. Please contact warehouse.");
        }

        // เก็บยอดเดิมไว้เช็ค (ใช้ Getter)
        $oldTotal = $order->getTotalAmount();
        $wasConfirmed = $order->getStatus() === OrderStatus::Confirmed;

        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

        // ------------------------------------------------------------------
        // ✅ [GUARD 2] Financial Control Check
        // คำนวณยอดใหม่ล่วงหน้าเพื่อเช็ควงเงิน
        // ------------------------------------------------------------------
        $newGrandTotal = 0;
        foreach ($dto->items as $itemDto) {
             if ($itemDto->quantity <= 0) continue;
             $product = $products[$itemDto->productId] ?? null;
             if ($product) {
                 $newGrandTotal += $product->price * $itemDto->quantity;
             }
        }

        // ถ้ายอดใหม่ > ยอดเดิม ให้เช็คเครดิต (ใช้ getCustomerId())
        if (($wasConfirmed || $dto->confirmOrder) && $newGrandTotal > $oldTotal) {
            $increaseAmount = $newGrandTotal - $oldTotal;
            // เมธอดนี้จะ Throw Exception ถ้าวงเงินไม่พอ
            $this->creditCheckService->canPlaceOrder($order->getCustomerId(), $increaseAmount);
        }

        return DB::transaction(function () use ($order, $dto, $products, $oldTotal, $orderId, $wasConfirmed) {

            // อัปเดตข้อมูล Header
            $order->updateDetails($dto->customerId, $dto->note, $dto->paymentTerms);

            // เคลียร์รายการเดิมและเพิ่มใหม่ (Aggregate Pattern)
            $order->clearItems();

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

            // บันทึกผ่าน Repository (จะจัดการเรื่อง Persistence ให้เอง)
            $this->orderRepository->save($order);

            // Logic Log ราคา (ใช้ Getter)
            $newTotal = $order->getTotalAmount();

            if ($order->getStatus() === OrderStatus::Confirmed && abs($oldTotal - $newTotal) > 0.01) {
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

            if ($isJustConfirmed) {
                OrderConfirmed::dispatch($orderId);
            }
            elseif ($wasConfirmed) {
                // ส่ง Event เพื่อให้ Logistics Sync ทำงาน
                OrderUpdated::dispatch($order);
            }

            return $order;
        });
    }
}
