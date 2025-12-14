<?php

namespace TmrEcosystem\Warehouse\Domain\Aggregates;

use Exception;

/**
 * StorageLocation Aggregate
 * เป็นตัวแทนของ "ตำแหน่งจัดเก็บ" ในทางธุรกิจ
 */
class StorageLocation
{
    public function __construct(
        private ?int $dbId,
        private string $uuid,
        private string $warehouseUuid,
        private string $code,
        private string $barcode,
        private string $type, // เก็บเป็น String หรือ Enum (เช่น 'PICKING', 'BULK', 'RECEIVING')
        private ?string $description,
        private bool $isActive,
        // ✅ [NEW] รองรับความจุและการเต็ม
        private ?float $maxCapacity = null, // null = ไม่จำกัด
        private bool $isFull = false
    ) {}

    /**
     * Factory Method: สร้าง Location ใหม่
     */
    public static function create(
        string $uuid,
        string $warehouseUuid,
        string $code,
        ?string $barcode = null, // ถ้าไม่ส่งมา ใช้ code เป็น barcode
        string $type = 'PICKING',
        ?string $description = null,
        // ✅ [NEW] รับค่าความจุตอนสร้าง (Optional)
        ?float $maxCapacity = null
    ): self {
        // Business Rule: Barcode ต้องมีค่า
        $finalBarcode = $barcode ?? $code;

        return new self(
            dbId: null,
            uuid: $uuid,
            warehouseUuid: $warehouseUuid,
            code: strtoupper(trim($code)), // บังคับตัวใหญ่เสมอ
            barcode: strtoupper(trim($finalBarcode)),
            type: $type,
            description: $description,
            isActive: true,
            // ✅ [NEW] กำหนดค่าเริ่มต้น
            maxCapacity: $maxCapacity,
            isFull: false
        );
    }

    /**
     * Business Logic: เปลี่ยนประเภทพื้นที่
     */
    public function changeType(string $newType): void
    {
        // Validation: อาจเช็คว่า Type นี้อนุญาตไหม หรือต้องเคลียร์ของก่อนเปลี่ยนประเภท
        $this->type = $newType;
    }

    /**
     * ✅ [NEW] Business Logic: ตรวจสอบว่า Location นี้รับของเพิ่มได้หรือไม่
     * * @param float $currentStockInLocation ยอดคงเหลือปัจจุบัน (รวมทุก Item) ใน Location นี้
     * @param float $incomingQuantity ยอดที่จะรับเข้าเพิ่ม
     * @return bool
     */
    public function canAccommodate(float $currentStockInLocation, float $incomingQuantity): bool
    {
        // 1. ถ้าถูก Lock ว่าเต็มแล้ว (Manual Override) -> ห้ามเติม
        if ($this->isFull) {
            return false;
        }

        // 2. ถ้าไม่ได้กำหนดความจุ (null) ถือว่ารับได้ตลอด (Unlimited)
        if (is_null($this->maxCapacity)) {
            return true;
        }

        // 3. ตรวจสอบว่าถ้ารับของใหม่เข้ามา ยอดรวมจะเกินขีดจำกัดไหม
        return ($currentStockInLocation + $incomingQuantity) <= $this->maxCapacity;
    }

    /**
     * Business Logic: สั่ง Lock พื้นที่ (เช่น เต็ม หรือ เสียหาย)
     */
    public function markAsFull(): void
    {
        $this->isFull = true;
    }

    public function markAsAvailable(): void
    {
        $this->isFull = false;
    }

    // --- Getters ---
    public function uuid(): string { return $this->uuid; }
    public function warehouseUuid(): string { return $this->warehouseUuid; }
    public function code(): string { return $this->code; }
    public function barcode(): string { return $this->barcode; }
    public function type(): string { return $this->type; }
    public function description(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }
    // ✅ [NEW] Getters
    public function getMaxCapacity(): ?float { return $this->maxCapacity; }
    public function isFull(): bool { return $this->isFull; }
}
