<?php

namespace TmrEcosystem\Stock\Domain\Aggregates;

use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;
use Exception;

class StockLevel
{
    private float $quantityOnHand;
    private float $quantityReserved;     // Hard Reserve
    private float $quantitySoftReserved; // Soft Reserve

    public function __construct(
        private ?int $dbId,
        private string $uuid,
        private string $companyId,
        private string $itemUuid,
        private string $warehouseUuid, // เก็บไว้เพื่อ Performance (Denormalize)
        private string $locationUuid,
        float $quantityOnHand = 0,
        float $quantityReserved = 0,
        float $quantitySoftReserved = 0
    ) {
        if ($quantityOnHand < 0 || $quantityReserved < 0 || $quantitySoftReserved < 0) {
            throw new Exception("Stock quantities cannot be negative.");
        }
        $this->quantityOnHand = $quantityOnHand;
        $this->quantityReserved = $quantityReserved;
        $this->quantitySoftReserved = $quantitySoftReserved;
    }

    // Factory Method ปรับปรุงใหม่
    public static function create(
        string $uuid,
        string $companyId,
        string $itemUuid,
        string $warehouseUuid,
        string $locationUuid
    ): self {
        return new self(null, $uuid, $companyId, $itemUuid, $warehouseUuid, $locationUuid, 0, 0, 0);
    }

    // --- Business Logic ---

    public function getAvailableQuantity(): float
    {
        return $this->quantityOnHand - ($this->quantityReserved + $this->quantitySoftReserved);
    }

    public function issue(float $quantityToIssue, ?string $userId, ?string $reference): StockMovement
    {
        if ($quantityToIssue <= 0) throw new Exception("Quantity must be positive.");
        if ($quantityToIssue > $this->getAvailableQuantity()) {
            throw new InsufficientStockException("Not enough available stock.");
        }

        $this->quantityOnHand -= $quantityToIssue;

        return StockMovement::create(
            stockLevelUuid: $this->uuid,
            userId: $userId,
            type: "ISSUE",
            quantityChange: -$quantityToIssue,
            quantityAfterMove: $this->quantityOnHand,
            reference: $reference
        );
    }

    public function receive(float $quantityToReceive, ?string $userId, ?string $reference): StockMovement
    {
        if ($quantityToReceive <= 0) throw new Exception("Quantity must be positive.");
        $this->quantityOnHand += $quantityToReceive;

        return StockMovement::create(
            stockLevelUuid: $this->uuid,
            userId: $userId,
            type: "RECEIPT",
            quantityChange: +$quantityToReceive,
            quantityAfterMove: $this->quantityOnHand,
            reference: $reference
        );
    }

    // ✅ [เพิ่มใหม่] ย้ายของออก (Transfer Source)
    public function transferOut(float $quantity, ?string $userId, string $toLocationCode): StockMovement
    {
        if ($quantity <= 0) throw new Exception("Quantity must be positive.");
        if ($quantity > $this->getAvailableQuantity()) {
            throw new InsufficientStockException("Not enough available stock to transfer.");
        }

        $this->quantityOnHand -= $quantity;

        return StockMovement::create(
            stockLevelUuid: $this->uuid,
            userId: $userId,
            type: "TRANSFER_OUT", // Type เฉพาะ
            quantityChange: -$quantity,
            quantityAfterMove: $this->quantityOnHand,
            reference: "To: " . $toLocationCode
        );
    }

    // ✅ [เพิ่มใหม่] รับของจากการย้าย (Transfer Destination)
    public function transferIn(float $quantity, ?string $userId, string $fromLocationCode): StockMovement
    {
        if ($quantity <= 0) throw new Exception("Quantity must be positive.");

        $this->quantityOnHand += $quantity;

        return StockMovement::create(
            stockLevelUuid: $this->uuid,
            userId: $userId,
            type: "TRANSFER_IN", // Type เฉพาะ
            quantityChange: +$quantity,
            quantityAfterMove: $this->quantityOnHand,
            reference: "From: " . $fromLocationCode
        );
    }

    public function reserve(float $quantityToReserve): void
    {
        if ($quantityToReserve <= 0) throw new Exception("Quantity must be positive.");
        if ($quantityToReserve > $this->getAvailableQuantity()) {
            throw new InsufficientStockException("Not enough available stock.");
        }
        $this->quantityReserved += $quantityToReserve;
    }

    // --- Soft Reserve Logic ---

    public function reserveSoft(float $quantity): void
    {
        if ($quantity <= 0) return;
        if ($this->getAvailableQuantity() < $quantity) {
            throw new InsufficientStockException("Stock not available for soft reservation.");
        }
        $this->quantitySoftReserved += $quantity;
    }

    public function commitReservation(float $quantity): void
    {
        if ($quantity <= 0) return;
        $this->quantitySoftReserved = max(0, $this->quantitySoftReserved - $quantity);
        $this->quantityReserved += $quantity;
    }

    public function releaseSoftReservation(float $quantity): void
    {
        if ($quantity <= 0) return;
        $this->quantitySoftReserved = max(0, $this->quantitySoftReserved - $quantity);
    }

    /**
     * ✅ [เพิ่มใหม่] ปลด Hard Reserve (กรณี Unload / Cancel Delivery)
     * ไม่เพิ่ม OnHand (เพราะของยังไม่ออก) แต่ลด Reserved คืนกลับเป็น Available
     */
    public function releaseHardReservation(float $quantity): void
    {
        if ($quantity <= 0) return;
        $this->quantityReserved = max(0, $this->quantityReserved - $quantity);
    }

    /**
     * ✅ [ปรับปรุง] Smart Ship (ตัดสต็อก)
     */
    public function shipReserved(float $quantity, ?string $userId, ?string $ref): StockMovement
    {
        if ($quantity <= 0) throw new Exception("Quantity must be positive.");
        if ($this->quantityOnHand < $quantity) throw new InsufficientStockException("Not enough physical stock.");

        $remainingToDeduct = $quantity;

        // 1. ตัด Hard Reserve ก่อน
        if ($this->quantityReserved > 0) {
            $deducted = min($this->quantityReserved, $remainingToDeduct);
            $this->quantityReserved -= $deducted;
            $remainingToDeduct -= $deducted;
        }
        // 2. ถ้ายังเหลือ ตัด Soft Reserve
        if ($remainingToDeduct > 0 && $this->quantitySoftReserved > 0) {
            $deducted = min($this->quantitySoftReserved, $remainingToDeduct);
            $this->quantitySoftReserved -= $deducted;
            $remainingToDeduct -= $deducted;
        }

        $this->quantityOnHand -= $quantity;

        return StockMovement::create(
            stockLevelUuid: $this->uuid,
            userId: $userId,
            type: "SHIPMENT",
            quantityChange: -$quantity,
            quantityAfterMove: $this->quantityOnHand,
            reference: $ref
        );
    }

    public function adjust(float $newQuantity, ?string $userId, ?string $reason): StockMovement
    {
        if ($newQuantity < 0) throw new Exception("New quantity cannot be negative.");
        $quantityChange = $newQuantity - $this->quantityOnHand;
        if ($quantityChange == 0) throw new Exception("No adjustment needed.");
        $this->quantityOnHand = $newQuantity;

        return StockMovement::create(
            stockLevelUuid: $this->uuid,
            userId: $userId,
            type: "ADJUST",
            quantityChange: $quantityChange,
            quantityAfterMove: $this->quantityOnHand,
            reference: $reason
        );
    }

    // Getters
    public function uuid(): string
    {
        return $this->uuid;
    }
    public function itemUuid(): string
    {
        return $this->itemUuid;
    }
    public function warehouseUuid(): string
    {
        return $this->warehouseUuid;
    }
    public function locationUuid(): string
    {
        return $this->locationUuid;
    }
    public function companyId(): string
    {
        return $this->companyId;
    }
    public function getQuantityOnHand(): float
    {
        return $this->quantityOnHand;
    }
    public function getQuantityReserved(): float
    {
        return $this->quantityReserved;
    }
    public function getQuantitySoftReserved(): float
    {
        return $this->quantitySoftReserved;
    }
    // ✅ [เพิ่มเมธอดนี้] เพื่อให้ Controller เรียกใช้ได้
    public function getLocationUuid(): string
    {
        return $this->locationUuid;
    }
}
