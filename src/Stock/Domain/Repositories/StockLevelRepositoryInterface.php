<?php

namespace TmrEcosystem\Stock\Domain\Repositories;

use TmrEcosystem\Stock\Domain\Aggregates\StockLevel;
use TmrEcosystem\Stock\Domain\Aggregates\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * "สัญญา" (Interface) สำหรับ StockLevel Repository
 */
interface StockLevelRepositoryInterface
{
    /**
     * สร้าง UUID ใหม่
     */
    public function nextUuid(): string;

    /**
     * ✅ เปลี่ยนจาก findByItemAndWarehouse เป็น findByLocation
     * เพราะตอนนี้ 1 Item ใน 1 Warehouse อาจมีหลาย Record (กระจายหลาย Location)
     * แต่ถ้าเราระบุ Location ด้วย จะเจอแค่ 1 Record แน่นอน
     */
    public function findByLocation(string $itemUuid, string $locationUuid, string $companyId): ?StockLevel;

    // (Optional) ถ้าอยากหาผลรวมของทั้ง Warehouse (สำหรับหน้า Dashboard)
    // public function sumByWarehouse(string $itemUuid, string $warehouseUuid): float;

    /**
     * บันทึก Aggregate (POPO) และ Movements ที่เกิดขึ้น
     * (นี่คือ Transactional Method)
     *
     * @param StockLevel $stockLevel
     * @param StockMovement[] $movements
     */
    public function save(StockLevel $stockLevel, array $movements): void;

    /**
     * (2. 👈 [ใหม่] ดึงข้อมูลแบบแบ่งหน้าสำหรับหน้า List)
     * (จะคืนค่าเป็น Paginator ของ StockLevelIndexData DTOs)
     */
    public function getPaginatedList(string $companyId, array $filters = []): LengthAwarePaginator;

    /**
     * ✅ [เพิ่มใหม่] ดึงรายการสต็อกที่ "หยิบได้" ทั้งหมดของสินค้านั้น
     * โดยเรียงลำดับตาม Location Type (Picking มาก่อน)
     * * @return Collection|StockLevel[]
     */
    public function findPickableStocks(string $itemUuid, string $warehouseUuid): Collection;

    // ✅ [เพิ่มบรรทัดนี้] ค้นหา StockLevel ที่มีการจองแบบ Soft Reserve ค้างอยู่
    public function findWithSoftReserve(string $itemUuid, string $warehouseUuid): iterable;

    // ✅ [เพิ่ม] ค้นหา Stock Level ที่มี Hard Reserve ของสินค้านี้ (เพื่อเตรียมตัดของ)
    /** @return StockLevel[] */
    public function findWithHardReserve(string $itemUuid, string $companyId): array;
}
