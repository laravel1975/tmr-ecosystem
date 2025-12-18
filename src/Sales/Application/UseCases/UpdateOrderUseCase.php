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
use TmrEcosystem\Sales\Domain\Services\CreditCheckService;
// ✅ Import Interface แทน Model
use TmrEcosystem\Sales\Application\Contracts\LogisticsStatusCheckerInterface;

class UpdateOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog,
        private CreditCheckService $creditCheckService,
        private LogisticsStatusCheckerInterface $logisticsStatusChecker // ✅ Inject Interface
    ) {}

    public function handle(string $orderId, UpdateOrderDto $dto): Order
    {
        Log::info("UpdateOrderUseCase: Starting update for Order ID {$orderId}");

        $order = $this->orderRepository->findById($orderId);
        if (!$order) throw new Exception("Order not found");

        if (in_array($order->getStatus(), [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot update finalized orders.");
        }

        // ✅ ใช้ Interface ตรวจสอบสถานะ Logistics (Clean Architecture)
        if ($this->logisticsStatusChecker->isPickingStarted($order->getId())) {
            throw new Exception("Cannot update order: Picking process has already started. Please contact warehouse.");
        }

        $oldTotal = $order->getTotalAmount();
        $wasConfirmed = $order->getStatus() === OrderStatus::Confirmed;

        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

        // Financial Control Check
        $newGrandTotal = 0;
        foreach ($dto->items as $itemDto) {
             if ($itemDto->quantity <= 0) continue;
             $product = $products[$itemDto->productId] ?? null;
             if ($product) {
                 $newGrandTotal += $product->price * $itemDto->quantity;
             }
        }

        if (($wasConfirmed || $dto->confirmOrder) && $newGrandTotal > $oldTotal) {
            $increaseAmount = $newGrandTotal - $oldTotal;
            $this->creditCheckService->canPlaceOrder($order->getCustomerId(), $increaseAmount);
        }

        return DB::transaction(function () use ($order, $dto, $products, $oldTotal, $orderId, $wasConfirmed) {

            $order->updateDetails($dto->customerId, $dto->note, $dto->paymentTerms);

            // Preserve State Logic (จากรอบที่แล้ว)
            $existingItemsMap = $order->getItems()
                ->mapWithKeys(fn($item) => [$item->id => $item->qtyShipped])
                ->toArray();

            $order->clearItems();

            foreach ($dto->items as $itemDto) {
                if ($itemDto->quantity <= 0) continue;

                $product = $products[$itemDto->productId] ?? null;
                if (!$product) continue;

                $preservedQtyShipped = 0;
                if ($itemDto->id && isset($existingItemsMap[$itemDto->id])) {
                    $preservedQtyShipped = $existingItemsMap[$itemDto->id];
                }

                $order->addItem(
                    productId: $product->id,
                    productName: $product->name,
                    price: $product->price,
                    quantity: $itemDto->quantity,
                    id: $itemDto->id,
                    qtyShipped: $preservedQtyShipped
                );
            }

            $isJustConfirmed = false;
            if ($dto->confirmOrder && $order->getStatus() === OrderStatus::Draft) {
                $order->confirm();
                $isJustConfirmed = true;
            }

            $this->orderRepository->save($order);

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

            if ($isJustConfirmed) {
                OrderConfirmed::dispatch($orderId);
            }
            elseif ($wasConfirmed) {
                OrderUpdated::dispatch($order);
            }

            return $order;
        });
    }
}
