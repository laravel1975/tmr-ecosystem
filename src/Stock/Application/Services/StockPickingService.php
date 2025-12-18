<?php

namespace TmrEcosystem\Stock\Application\Services;

use TmrEcosystem\Stock\Application\Contracts\StockPickingServiceInterface;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
// ใช้ Model ของ Warehouse เพื่อดึงรหัส Location (ถ้าแยก Module เคร่งครัดอาจต้องใช้ Repository ของ Warehouse)
use TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\StorageLocationModel;

class StockPickingService implements StockPickingServiceInterface
{
    public function __construct(
        private StockLevelRepositoryInterface $stockLevelRepository
    ) {}

    public function planPicking(string $warehouseId, array $items): array
    {
        $pickingPlan = [];

        foreach ($items as $item) {
            $itemId = $item['product_id'];
            $qtyNeeded = (float) $item['quantity'];

            if ($qtyNeeded <= 0) continue;

            // 1. ดึงข้อมูล Stock ที่หยิบได้ (เรียงตาม Priority: Picking Zone -> General)
            // Repository ต้องมี method findPickableStocks ตามที่ทำไว้ก่อนหน้า
            $availableStocks = $this->stockLevelRepository->findPickableStocks($itemId, $warehouseId);

            $qtyRemaining = $qtyNeeded;

            foreach ($availableStocks as $stock) {
                if ($qtyRemaining <= 0) break;

                // คำนวณยอดที่หยิบได้จากกองนี้ (Available = OnHand - Reserved)
                // หรือถ้าจะหยิบจาก OnHand เลย (เพราะถือว่าจองแล้ว) ให้ใช้ getQuantityOnHand()
                $qtyInLocation = $stock->getQuantityOnHand();

                if ($qtyInLocation <= 0) continue;

                $take = min($qtyInLocation, $qtyRemaining);

                // 2. ดึง Location Code เพื่อแสดงผล
                $locationCode = 'UNKNOWN';
                $locationUuid = $stock->getLocationUuid();

                if ($locationUuid) {
                    $location = StorageLocationModel::find($locationUuid);
                    if ($location) {
                        $locationCode = $location->code;
                    }
                }

                $pickingPlan[] = [
                    'product_id' => $itemId,
                    'location_uuid' => $locationUuid,
                    'location_code' => $locationCode,
                    'quantity' => $take
                ];

                $qtyRemaining -= $take;
            }

            // กรณีของไม่พอ (Backorder / Not Found)
            if ($qtyRemaining > 0) {
                $pickingPlan[] = [
                    'product_id' => $itemId,
                    'location_uuid' => null,
                    'location_code' => 'N/A (Not Found)',
                    'quantity' => $qtyRemaining
                ];
            }
        }

        return $pickingPlan;
    }
}
