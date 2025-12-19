<?php

namespace TmrEcosystem\Sales\Domain\Aggregates;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TmrEcosystem\Sales\Domain\Entities\OrderItem;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use Exception;

class Order
{
    // Properties
    private string $id;
    private string $orderNumber;
    private string $customerId; // ✅ Property เจ้าปัญหา
    private ?string $salespersonId;
    private string $companyId;
    private string $warehouseId;
    private OrderStatus $status;
    private Collection $items;
    private float $totalAmount;
    private string $note = '';
    private string $paymentTerms = 'immediate';

    // ✅ Constructor: ต้องกำหนดค่า customerId ทันที
    public function __construct(
        string $customerId,
        string $companyId = 'DEFAULT_COMPANY',
        string $warehouseId = 'DEFAULT_WAREHOUSE',
        ?string $salespersonId = null
    ) {
        $this->id = (string) Str::uuid();
        $this->customerId = $customerId; // ✅ ห้ามลืมบรรทัดนี้
        $this->companyId = $companyId;
        $this->warehouseId = $warehouseId;
        $this->salespersonId = $salespersonId;

        $this->status = OrderStatus::Draft;
        $this->items = collect([]);
        $this->totalAmount = 0;
        $this->orderNumber = 'DRAFT';
    }

    // --- Getters ---
    public function getId(): string { return $this->id; }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getCompanyId(): string { return $this->companyId; }
    public function getWarehouseId(): string { return $this->warehouseId; }
    public function getSalespersonId(): ?string { return $this->salespersonId; }
    public function getStatus(): OrderStatus { return $this->status; }
    public function getItems(): Collection { return $this->items; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getOrderNumber(): string { return $this->orderNumber; }
    public function getNote(): string { return $this->note; }
    public function getPaymentTerms(): string { return $this->paymentTerms; }

    // --- Domain Methods (คงเดิม) ---
    public function addItem(string $productId, string $productName, float $price, int $quantity, ?string $id = null, int $qtyShipped = 0): void
    {
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot modify finalized order.");
        }
        if ($quantity < $qtyShipped) {
            throw new Exception("Cannot set quantity lower than shipped quantity.");
        }

        $item = new OrderItem(
            productId: $productId,
            productName: $productName,
            unitPrice: $price,
            quantity: $quantity,
            id: $id,
            qtyShipped: $qtyShipped
        );

        $this->items->push($item);
        $this->recalculateTotal();
    }

    public function clearItems(): void
    {
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot clear items of finalized order.");
        }
        $this->items = collect([]);
        $this->recalculateTotal();
    }

    public function updateDetails(string $customerId, ?string $note, ?string $paymentTerms): void
    {
        $this->customerId = $customerId;
        $this->note = $note ?? '';
        $this->paymentTerms = $paymentTerms ?? 'immediate';
    }

    public function confirm(): void
    {
        if ($this->status !== OrderStatus::Draft) throw new Exception("Order already confirmed/cancelled.");
        if ($this->items->isEmpty()) throw new Exception("Cannot confirm empty order.");
        $this->status = OrderStatus::Confirmed;
    }

    public function cancel(): void
    {
        if ($this->status === OrderStatus::Completed) throw new Exception("Cannot cancel completed order.");
        $this->status = OrderStatus::Cancelled;
    }

    public function updateItemShipmentStatus(string $itemId, int $totalQtyShipped): void
    {
        $item = $this->items->first(fn($i) => $i->id === $itemId);
        if ($item) {
            $item->updateShippedQty($totalQtyShipped);
            $this->reassessStatus();
        }
    }

    private function reassessStatus(): void
    {
        if ($this->items->isEmpty()) return;
        $allFullyShipped = $this->items->every(fn($item) => $item->isFullyShipped());
        $someShipped = $this->items->contains(fn($item) => $item->qtyShipped > 0);

        if ($allFullyShipped) {
            $this->status = OrderStatus::Completed;
        } elseif ($someShipped) {
            $this->status = OrderStatus::PartiallyShipped;
        } else {
            $this->status = OrderStatus::Confirmed;
        }
    }

    private function recalculateTotal(): void
    {
        $this->totalAmount = $this->items->sum(fn($item) => $item->total());
    }

    // ✅ Reconstitute: สร้าง Object จาก DB โดยเรียก Constructor ให้ถูกต้อง
    public static function reconstitute(
        string $id,
        string $orderNumber,
        string $customerId, // รับค่าเข้ามา
        string $companyId,
        string $warehouseId,
        ?string $salespersonId,
        string $statusString,
        float $totalAmount,
        iterable $itemsData,
        string $note = '',
        string $paymentTerms = 'immediate'
    ): self {
        // 1. เรียก Constructor เพื่อ Initialize Properties หลัก (รวมถึง customerId)
        $instance = new self($customerId, $companyId, $warehouseId, $salespersonId);

        // 2. เติมข้อมูลส่วนที่เหลือ
        $instance->id = $id;
        $instance->orderNumber = $orderNumber;
        $instance->status = OrderStatus::from($statusString);
        $instance->totalAmount = $totalAmount;
        $instance->note = $note;
        $instance->paymentTerms = $paymentTerms;

        // 3. Map Items (Array -> Entities)
        $instance->items = collect($itemsData)->map(fn($item) => OrderItem::fromStorage($item));

        return $instance;
    }
}
