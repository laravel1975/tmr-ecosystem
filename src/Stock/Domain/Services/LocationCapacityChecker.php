<?php

namespace TmrEcosystem\Stock\Domain\Services;

use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Warehouse\Domain\Repositories\StorageLocationRepositoryInterface;
use Exception;

class LocationCapacityChecker
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private StorageLocationRepositoryInterface $locationRepo
    ) {}

    /**
     * ตรวจสอบว่า Location รับของเพิ่มได้หรือไม่
     * @throws Exception ถ้าความจุไม่พอ
     */
    public function check(string $locationUuid, float $incomingQty, string $companyId): void
    {
        // 1. ดึงข้อมูล Location
        $location = $this->locationRepo->findByUuid($locationUuid);
        if (!$location) {
            throw new Exception("Location not found.");
        }

        // ถ้า Location ไม่จำกัดความจุ ก็ผ่านเลย
        // (เมธอด getMaxCapacity ถูกต้องแล้วตามที่สร้างไว้)
        if (is_null($location->getMaxCapacity())) {
            return;
        }

        // 2. คำนวณยอดของที่มีอยู่ทั้งหมดใน Location นี้
        $currentTotalStock = $this->stockRepo->sumQuantityInLocation($locationUuid, $companyId);

        // 3. ตรวจสอบกฏ
        if (!$location->canAccommodate($currentTotalStock, $incomingQty)) {
            $remaining = $location->getMaxCapacity() - $currentTotalStock;

            // ✅ แก้ไข: เปลี่ยนจาก $location->getCode() เป็น $location->code()
            // เพื่อให้ตรงกับ Getter ใน StorageLocation.php
            throw new Exception(
                "Storage Limit Exceeded: Location {$location->code()} receives {$incomingQty} but space remains only {$remaining}."
            );
        }
    }
}
