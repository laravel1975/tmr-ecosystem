<?php

namespace TmrEcosystem\Stock\Domain\Repositories;

use TmrEcosystem\Stock\Domain\Aggregates\StockLevel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StockLevelRepositoryInterface
{
    public function nextUuid(): string;

    /**
     * ค้นหา StockLevel สำหรับ Item ใน Warehouse
     * กรณีมีหลาย Location อาจจะคืนค่า Location ที่เหมาะสมที่สุด (เช่น มี Soft Reserve ค้างอยู่)
     */
    public function findByItemAndWarehouse(string $itemUuid, string $warehouseUuid): ?StockLevel;

    /**
     * @return Collection<int, StockLevel>
     */
    public function findWithAvailableStock(string $itemUuid, string $warehouseUuid): Collection;

    public function findByLocation(string $itemUuid, string $locationUuid, string $companyId): ?StockLevel;

    public function save(StockLevel $stockLevel, array $movements): void;

    public function getPaginatedList(string $companyId, array $filters = []): LengthAwarePaginator;

    public function sumQuantityInLocation(string $locationUuid, string $companyId): float;

    public function findWithHardReserve(string $itemUuid, string $companyId): array;

    // ✅ [เพิ่มใหม่] สำหรับ Picking Strategy (ReserveStockUseCase)
    public function findPickableStocks(string $itemUuid, string $warehouseUuid): Collection;

    // ✅ [เพิ่มใหม่] สำหรับ Release Strategy (ReleaseStockUseCase)
    public function findWithSoftReserve(string $itemUuid, string $warehouseUuid): iterable;
}
