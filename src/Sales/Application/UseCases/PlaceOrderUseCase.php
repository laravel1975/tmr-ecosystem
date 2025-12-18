<?php

namespace TmrEcosystem\Sales\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Sales\Application\DTOs\CreateOrderDto;
use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Services\ProductCatalogInterface;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use TmrEcosystem\Communication\Infrastructure\Persistence\Models\CommunicationMessage;
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\Sales\Domain\Services\CreditCheckService;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
use TmrEcosystem\Sales\Application\DTOs\OrderItemSnapshotDto;
// ✅ Import Interface ใหม่
use TmrEcosystem\Sales\Application\Contracts\StockReservationInterface;

class PlaceOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog,
        private CreditCheckService $creditCheckService,
        private StockReservationInterface $stockReservation // ✅ Inject Reservation Service
    ) {}

    /**
     * @throws Exception
     */
    public function handle(CreateOrderDto $dto): Order
    {
        // 1. Prepare Data
        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

        // 2. Resolve Salesperson
        $finalSalespersonId = $dto->salespersonId;
        if (!$finalSalespersonId) {
            $customer = Customer::find($dto->customerId);
            $finalSalespersonId = $customer ? $customer->default_salesperson_id : null;
        }

        // 3. Financial Control
        $estimatedTotal = 0;
        foreach ($dto->items as $itemDto) {
            $product = $products[$itemDto->productId] ?? null;
            if ($product) {
                $estimatedTotal += $product->price * $itemDto->quantity;
            }
        }

        if ($estimatedTotal > 0) {
            $this->creditCheckService->canPlaceOrder($dto->customerId, $estimatedTotal);
        }

        return DB::transaction(function () use ($dto, $products, $finalSalespersonId) {

            // 4. Create Aggregate Root
            $order = new Order(
                customerId: $dto->customerId,
                companyId: $dto->companyId,
                warehouseId: $dto->warehouseId,
                salespersonId: $finalSalespersonId
            );

            // 5. Add Items & Prepare for Reservation
            $reservationItems = [];

            foreach ($dto->items as $itemDto) {
                $product = $products[$itemDto->productId] ?? null;

                if (!$product) {
                    throw new Exception("Product ID {$itemDto->productId} not found in catalog.");
                }

                $order->addItem(
                    productId: $product->id,
                    productName: $product->name,
                    price: $product->price,
                    quantity: $itemDto->quantity
                );

                // เก็บข้อมูลเตรียมจอง
                $reservationItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $itemDto->quantity
                ];
            }

            // 6. Update Details
            $order->updateDetails(
                customerId: $dto->customerId,
                note: $dto->note ?? null,
                paymentTerms: $dto->paymentTerms ?? null
            );

            // 7. Confirm Order & Reserve Stock
            if ($dto->confirmOrder) {
                // ✅ จองสินค้าทันที (ถ้าของไม่พอจะ Error และ Rollback ทั้งหมด)
                $this->stockReservation->reserveItems(
                    orderId: $order->getId(),
                    items: $reservationItems,
                    warehouseId: $dto->warehouseId
                );

                $order->confirm();
            } else {
                // ถ้าเป็น Draft อาจจะยังไม่จอง หรือจองแบบมี Expiry (แล้วแต่ Business Rule)
                // ในที่นี้สมมติว่า Draft ยังไม่จอง
            }

            // 8. Save Aggregate
            $this->orderRepository->save($order);

            // 9. Auto Log
            CommunicationMessage::create([
                'user_id' => Auth::id(),
                'body' => "Order Created (สร้างใบสั่งขาย) #{$order->getOrderNumber()}",
                'type' => 'notification',
                'model_type' => 'sales_order',
                'model_id' => $order->getId()
            ]);

            // 10. Dispatch Event
            if ($order->getStatus() === OrderStatus::Confirmed) {
                $itemsSnapshot = $order->getItems()->map(fn($item) => new OrderItemSnapshotDto(
                    productId: $item->productId,
                    productName: $item->productName,
                    quantity: $item->quantity,
                    unitPrice: $item->unitPrice
                ))->toArray();

                $orderSnapshot = new OrderSnapshotDto(
                    orderId: $order->getId(),
                    orderNumber: $order->getOrderNumber(),
                    customerId: $order->getCustomerId(),
                    companyId: $order->getCompanyId(),
                    warehouseId: $order->getWarehouseId(),
                    items: $itemsSnapshot,
                    note: $order->getNote()
                );

                OrderConfirmed::dispatch($order->getId(), $orderSnapshot);
            }

            return $order;
        });
    }
}
