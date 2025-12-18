<?php

namespace TmrEcosystem\Sales\Domain\Aggregates;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TmrEcosystem\Sales\Domain\Entities\OrderItem;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use Exception;

class Order
{
    // Properties (คงเดิม)
    private string $id;
    private string $orderNumber;
    private string $customerId;
    private ?string $salespersonId;
    private string $companyId;
    private string $warehouseId;
    private OrderStatus $status;
    private Collection $items;
    private float $totalAmount;
    private string $note = '';
    private string $paymentTerms = 'immediate';

    // ... (Constructor และ Getters คงเดิม) ...
    public function getId(): string { return $this->id; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getCompanyId(): string { return $this->companyId; }
    public function getWarehouseId(): string { return $this->warehouseId; }
    public function getSalespersonId(): ?string { return $this->salespersonId; }
    public function getStatus(): OrderStatus { return $this->status; }
    public function getItems(): Collection { return $this->items; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getOrderNumber(): string { return $this->orderNumber; }
    public function getNote(): string { return $this->note; }
    public function getPaymentTerms(): string { return $this->paymentTerms; }

    // --- Existing Methods (addItem, clearItems, etc.) ---

    public function addItem(string $productId, string $productName, float $price, int $quantity, ?string $id = null, int $qtyShipped = 0): void
    {
        // (Logic ตามที่แก้ไปรอบก่อนหน้า)
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot modify finalized order.");
        }
        if ($quantity < $qtyShipped) {
            throw new Exception("Cannot set quantity lower than shipped quantity.");
        }
        $item = new OrderItem($productId, $productName, $price, $quantity, $id, $qtyShipped);
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

    // ✅ [เพิ่มใหม่] Method สำหรับรับข้อมูลการจัดส่งจาก Logistics
    public function updateItemShipmentStatus(string $itemId, int $totalQtyShipped): void
    {
        if ($this->status === OrderStatus::Cancelled) {
            throw new Exception("Cannot update shipment for a cancelled order.");
        }

        // 1. ค้นหา Item
        $item = $this->items->first(fn(OrderItem $i) => $i->id === $itemId);
        if (!$item) {
            // อาจจะ Log warning หรือ ignore ถ้าหาไม่เจอ (กรณี Data inconsistency)
            return;
        }

        // 2. อัปเดตข้อมูลใน Entity
        $item->updateShippedQty($totalQtyShipped);

        // 3. ประเมินสถานะ Order ทั้งใบใหม่ (State Transition)
        $this->reassessStatus();
    }

    // ✅ [เพิ่มใหม่] Logic การเปลี่ยนสถานะ (State Machine)
    private function reassessStatus(): void
    {
        if ($this->items->isEmpty()) return;

        $allFullyShipped = $this->items->every(fn(OrderItem $item) => $item->isFullyShipped());
        $someShipped = $this->items->contains(fn(OrderItem $item) => $item->qtyShipped > 0);

        if ($allFullyShipped) {
            $this->status = OrderStatus::Completed;
        } elseif ($someShipped) {
            $this->status = OrderStatus::PartiallyShipped;
        } else {
            // ถ้ายังไม่ส่งเลย ให้กลับไปเป็น Confirmed (เผื่อกรณียกเลิกใบส่งของ)
            $this->status = OrderStatus::Confirmed;
        }
    }

    private function recalculateTotal(): void
    {
        $this->totalAmount = $this->items->sum(fn(OrderItem $item) => $item->total());
    }

    // Reconstitute method... (คงเดิม)
    public static function reconstitute(
        string $id,
        string $orderNumber,
        string $customerId,
        string $companyId,
        string $warehouseId,
        ?string $salespersonId,
        string $statusString,
        float $totalAmount,
        iterable $itemsData,
        string $note = '',
        string $paymentTerms = 'immediate'
    ): self {
        $instance = new self($customerId, $companyId, $warehouseId, $salespersonId);
        $instance->id = $id;
        $instance->orderNumber = $orderNumber;
        $instance->status = OrderStatus::from($statusString);
        $instance->totalAmount = $totalAmount;
        $instance->note = $note;
        $instance->paymentTerms = $paymentTerms;
        $instance->items = collect($itemsData)->map(fn($item) => OrderItem::fromStorage($item));
        return $instance;
    }
}
