<?php

namespace TmrEcosystem\Sales\Domain\Aggregates;

use Illuminate\Support\Collection;
use TmrEcosystem\Sales\Domain\Entities\OrderItem;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use Exception;
use Illuminate\Support\Str;

class Order
{
    // Properties
    private string $id;
    private string $orderNumber;
    private string $customerId;

    // ✅ [เพิ่ม] Context Properties
    private string $companyId;
    private string $warehouseId;

    private OrderStatus $status;
    private Collection $items;
    private float $totalAmount;
    private string $note = '';
    private string $paymentTerms = 'immediate';

    // ✅ [ปรับ] รับ companyId และ warehouseId
    public function __construct(
        string $customerId,
        string $companyId = 'DEFAULT_COMPANY',  // Default ไว้กัน Error (แต่ควรส่งค่าจริง)
        string $warehouseId = 'DEFAULT_WAREHOUSE'
    ) {
        $this->id = (string) Str::uuid();
        $this->customerId = $customerId;
        $this->companyId = $companyId;
        $this->warehouseId = $warehouseId;

        $this->status = OrderStatus::Draft;
        $this->items = collect([]);
        $this->totalAmount = 0;
        $this->orderNumber = 'DRAFT';
    }

    // --- Getters ---
    public function getId(): string
    {
        return $this->id;
    }
    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    // ✅ [เพิ่ม] Getters ที่ Repository เรียกหา
    public function getCompanyId(): string
    {
        return $this->companyId;
    }
    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }
    public function getItems(): Collection
    {
        return $this->items;
    }
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }
    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }
    public function getNote(): string
    {
        return $this->note;
    }
    public function getPaymentTerms(): string
    {
        return $this->paymentTerms;
    }

    // --- Domain Behaviors ---

    public function addItem(string $productId, string $productName, float $price, int $quantity, ?string $id = null): void
    {
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot modify finalized order.");
        }

        // ✅ FIX: ใช้ Named Arguments เพื่อความถูกต้องแม่นยำ
        // และ Cast $id จาก string เป็น int (เพราะ OrderItem รับ ?int)
        $item = new OrderItem(
            productId: $productId,
            productName: $productName,
            unitPrice: $price,     // ระบุชื่อให้ตรงกับ OrderItem
            quantity: $quantity,
            id: $id ? (int) $id : null, // แปลง string เป็น int
            qtyShipped: 0
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
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot update details of finalized order.");
        }
        $this->customerId = $customerId;
        $this->note = $note ?? '';
        $this->paymentTerms = $paymentTerms ?? 'immediate';
    }

    public function confirm(): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new Exception("Order is already confirmed or cancelled.");
        }
        if ($this->items->isEmpty()) {
            throw new Exception("Cannot confirm an empty order.");
        }
        $this->status = OrderStatus::Confirmed;
    }

    public function cancel(): void
    {
        if ($this->status === OrderStatus::Completed) {
            throw new Exception("Cannot cancel a completed order.");
        }
        if ($this->status === OrderStatus::Cancelled) {
            throw new Exception("Order is already cancelled.");
        }
        $this->status = OrderStatus::Cancelled;
    }

    private function recalculateTotal(): void
    {
        $this->totalAmount = $this->items->sum(fn(OrderItem $item) => $item->total());
    }

    // ✅ [ปรับ] reconstitute ให้รับค่า Context กลับมาด้วย
    public static function reconstitute(
        string $id,
        string $orderNumber,
        string $customerId,
        // เพิ่มพารามิเตอร์รับค่ากลับ
        string $companyId,
        string $warehouseId,
        string $statusString,
        float $totalAmount,
        iterable $itemsData,
        string $note = '',
        string $paymentTerms = 'immediate'
    ): self {
        $instance = new self($customerId, $companyId, $warehouseId);

        $instance->id = $id;
        $instance->orderNumber = $orderNumber;
        // companyId, warehouseId ถูก set ใน constructor แล้ว

        $instance->status = OrderStatus::from($statusString);
        $instance->totalAmount = $totalAmount;
        $instance->note = $note;
        $instance->paymentTerms = $paymentTerms;
        $instance->items = collect($itemsData)->map(fn($item) => OrderItem::fromStorage($item));

        return $instance;
    }
}
