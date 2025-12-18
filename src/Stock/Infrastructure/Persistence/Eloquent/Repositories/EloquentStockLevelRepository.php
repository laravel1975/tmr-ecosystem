<?php

namespace TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use TmrEcosystem\Stock\Domain\Aggregates\StockLevel;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockLevelModel;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\StockLevelMapper;
use TmrEcosystem\Stock\Application\DTOs\StockLevelIndexData;

class EloquentStockLevelRepository implements StockLevelRepositoryInterface
{
    public function __construct(
        protected StockLevelModel $model
    ) {}

    public function nextUuid(): string
    {
        return (string) Str::uuid();
    }

    /**
     * ค้นหา StockLevel จาก Location ที่ระบุ (ใช้สำหรับการรับของ/ย้ายของ/ปรับปรุงยอด)
     */
    public function findByLocation(string $itemUuid, string $locationUuid, string $companyId): ?StockLevel
    {
        $model = $this->model->newQuery()
            ->where('item_uuid', $itemUuid)
            ->where('location_uuid', $locationUuid)
            ->where('company_id', $companyId)
            ->first();

        if (!$model) {
            return null;
        }

        return StockLevelMapper::toDomain($model);
    }

    /**
     * บันทึกข้อมูล Aggregate Root และ Movements ภายใน Transaction
     */
    public function save(StockLevel $stockLevel, array $movements): void
    {
        DB::transaction(function () use ($stockLevel, $movements) {
            // 1. แปลง Aggregate กลับเป็น Model Data
            $levelData = StockLevelMapper::toPersistence($stockLevel);

            // 2. บันทึก Stock Level (Update หรือ Create)
            $this->model->newQuery()->updateOrCreate(
                ['uuid' => $stockLevel->uuid()],
                $levelData
            );

            // 3. บันทึก Movements (Audit Log)
            foreach ($movements as $movement) {
                $movementModel = StockLevelMapper::movementToPersistence($movement);
                $movementModel->save();
            }
        });
    }

    /**
     * ดึงข้อมูลสำหรับแสดงผลในหน้า List (พร้อม Pagination และ Search)
     */
    public function getPaginatedList(string $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->join('inventory_items', 'stock_levels.item_uuid', '=', 'inventory_items.uuid')
            ->join('warehouses', 'stock_levels.warehouse_uuid', '=', 'warehouses.uuid')
            ->join('warehouse_storage_locations', 'stock_levels.location_uuid', '=', 'warehouse_storage_locations.uuid')
            ->where('stock_levels.company_id', $companyId)
            ->select(
                'stock_levels.uuid as stock_level_uuid',
                'stock_levels.item_uuid',
                'stock_levels.warehouse_uuid',
                'stock_levels.location_uuid',
                'stock_levels.quantity_on_hand',
                'stock_levels.quantity_reserved',
                'stock_levels.quantity_soft_reserved',
                'inventory_items.name as item_name',
                'inventory_items.part_number as item_part_number',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
                'warehouse_storage_locations.code as location_code',
                'warehouse_storage_locations.type as location_type'
            );

        // Filter Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('inventory_items.part_number', 'like', "%{$search}%")
                    ->orWhere('inventory_items.name', 'like', "%{$search}%")
                    ->orWhere('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouse_storage_locations.code', 'like', "%{$search}%");
            });
        }

        // Filter Warehouse
        if (!empty($filters['warehouse_uuid']) && $filters['warehouse_uuid'] !== 'all') {
            $query->where('stock_levels.warehouse_uuid', $filters['warehouse_uuid']);
        }

        $paginatedResults = $query->paginate(15)->withQueryString();

        // Transform results to DTO
        $paginatedResults->setCollection(
            $paginatedResults->getCollection()->map(function ($result) {
                $onHand = (float) $result->quantity_on_hand;
                $hardReserved = (float) $result->quantity_reserved;
                $softReserved = (float) ($result->quantity_soft_reserved ?? 0);
                $available = $onHand - ($hardReserved + $softReserved);

                return new StockLevelIndexData(
                    stock_level_uuid: $result->stock_level_uuid,
                    item_uuid: $result->item_uuid ?? '',
                    warehouse_uuid: $result->warehouse_uuid ?? '',
                    location_uuid: $result->location_uuid ?? '',
                    item_part_number: $result->item_part_number,
                    item_name: $result->item_name,
                    warehouse_code: $result->warehouse_code,
                    warehouse_name: $result->warehouse_name,
                    location_code: $result->location_code ?? 'N/A',
                    location_type: $result->location_type ?? 'UNKNOWN',
                    quantity_on_hand: $onHand,
                    quantity_reserved: $hardReserved,
                    quantity_soft_reserved: $softReserved,
                    quantity_available: $available
                );
            })
        );

        return $paginatedResults;
    }

    /**
     * ดึงรายการสต็อกที่ "หยิบได้" ทั้งหมดของสินค้านั้น
     * เรียงลำดับความสำคัญ: PICKING -> อื่นๆ -> GENERAL
     */
    public function findPickableStocks(string $itemUuid, string $warehouseUuid): Collection
    {
        $models = $this->model->newQuery()
            ->join('warehouse_storage_locations', 'stock_levels.location_uuid', '=', 'warehouse_storage_locations.uuid')
            ->where('stock_levels.item_uuid', $itemUuid)
            ->where('stock_levels.warehouse_uuid', $warehouseUuid)
            ->where('stock_levels.quantity_on_hand', '>', 0) // ต้องมีของจริง
            ->where('warehouse_storage_locations.is_active', true)
            // กรอง Location Type ที่ห้ามหยิบ
            ->whereNotIn('warehouse_storage_locations.type', ['DAMAGED', 'RETURN', 'INBOUND'])

            // 🔥 เรียงลำดับ Strategy: หยิบจากโซน Picking ก่อน, General เอาไว้ทีหลัง
            ->orderByRaw("
                CASE
                    WHEN warehouse_storage_locations.type = 'PICKING' THEN 1
                    WHEN warehouse_storage_locations.code = 'GENERAL' THEN 99
                    ELSE 2
                END ASC
            ")
            // FIFO (First-In, First-Out) ตามวันที่สร้าง
            ->orderBy('stock_levels.created_at', 'asc')
            ->select('stock_levels.*') // Select กลับมาเฉพาะตาราง stock_levels
            ->get();

        return $models->map(fn($model) => StockLevelMapper::toDomain($model));
    }

    /**
     * ✅ [สำคัญ] ค้นหา StockLevel ที่มี Soft Reserve ค้างอยู่ (สำหรับ Release Stock)
     */
    public function findWithSoftReserve(string $itemUuid, string $warehouseUuid): iterable
    {
        $models = $this->model->newQuery()
            ->where('item_uuid', $itemUuid)
            ->where('warehouse_uuid', $warehouseUuid)
            ->where('quantity_soft_reserved', '>', 0) // หาเฉพาะที่มียอดจอง
            ->orderBy('quantity_soft_reserved', 'desc') // เรียงจากยอดจองมากไปน้อย (หรือ FIFO ก็ได้)
            ->get();

        return $models->map(fn($model) => StockLevelMapper::toDomain($model));
    }

    /**
     * ค้นหา Stock Level ที่มี Hard Reserve (สำหรับตัดของส่ง - Shipment)
     */
    public function findWithHardReserve(string $itemUuid, string $companyId): array
    {
        $models = $this->model->newQuery()
            ->where('item_uuid', $itemUuid)
            ->where('company_id', $companyId)
            ->where('quantity_reserved', '>', 0) // หาที่มี Hard Reserve
            ->orderBy('created_at', 'asc') // FIFO
            ->get();

        return $models->map(fn($m) => StockLevelMapper::toDomain($m))->toArray();
    }

    /**
     * คำนวณยอดรวมสินค้าใน Location นั้นๆ
     */
    public function sumQuantityInLocation(string $locationUuid, string $companyId): float
    {
        return (float) $this->model->newQuery()
            ->where('location_uuid', $locationUuid)
            ->where('company_id', $companyId)
            ->sum('quantity_on_hand');
    }
}
