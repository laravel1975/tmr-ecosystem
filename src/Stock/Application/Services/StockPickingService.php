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

    /**
     * คำนวณแผนการตัดสต็อกเมื่อมีการจัดส่งสินค้า (Shipment)
     * โดยจะไล่ตัดจาก Location ตามลำดับ Priority (FIFO/FEFO)
     */
    public function calculateShipmentDeductionPlan(string $itemId, string $warehouseId, float $quantityNeeded): array
    {
        $deductionPlan = [];
        $qtyRemaining = $quantityNeeded;

        if ($qtyRemaining <= 0) {
            return [];
        }

        // 1. ดึงข้อมูล Stock ที่มีอยู่ (ควรใช้ Logic เดียวกับการจอง คือ FIFO/FEFO)
        $availableStocks = $this->stockLevelRepository->findPickableStocks($itemId, $warehouseId);

        foreach ($availableStocks as $stock) {
            if ($qtyRemaining <= 0) break;

            // ตรวจสอบยอดที่สามารถตัดได้ (ในกรณี Shipment เราอาจจะตัดจาก Reserved หรือ OnHand ขึ้นอยู่กับ Flow)
            // สมมติว่าตัดจากยอดที่มีอยู่จริง (On Hand)
            $qtyAvailable = $stock->getQuantityOnHand();

            if ($qtyAvailable <= 0) continue;

            $take = min($qtyAvailable, $qtyRemaining);

            $deductionPlan[] = [
                'location_uuid' => $stock->getLocationUuid(),
                'quantity' => $take
            ];

            $qtyRemaining -= $take;
        }

        // กรณีที่มีสต็อกไม่พอตัด ระบบอาจจะต้องจัดการต่อ (เช่น ยอมให้ติดลบ หรือแจ้งเตือน)
        // ในที่นี้เราคืนค่าเท่าที่หาได้ไปก่อน

        return $deductionPlan;
    }

    /**
     * แนะนำ Location ที่ควรหยิบสินค้า สำหรับสินค้า 1 รายการ
     * (ถูกเรียกโดย Logistics CreatePickingSlipUseCase)
     */
    public function suggestPickingLocations(string $productId, float $quantity, string $warehouseId): array
    {
        $suggestions = [];
        $qtyNeeded = $quantity;

        if ($qtyNeeded <= 0) {
            return [];
        }

        // 1. ดึงข้อมูล Stock ที่หยิบได้ (FIFO/FEFO)
        $availableStocks = $this->stockLevelRepository->findPickableStocks($productId, $warehouseId);

        $qtyRemaining = $qtyNeeded;

        foreach ($availableStocks as $stock) {
            if ($qtyRemaining <= 0) break;

            // คำนวณยอดที่หยิบได้
            $qtyInLocation = $stock->getQuantityOnHand();
            // Note: หากระบบมีการจอง (Reserved) ควรใช้ $stock->getAvailableQuantity()
            // แต่ถ้าระบบนี้ใช้การตัด Stock ตอน Picking เลย ก็ใช้ Hand ได้

            if ($qtyInLocation <= 0) continue;

            $take = min($qtyInLocation, $qtyRemaining);

            // 2. ดึง Location Data
            // Note: ควรทำผ่าน Repository ถ้ายึดหลัก DDD เคร่งครัด แต่เพื่อความรวดเร็วใช้ Model ได้
            $locationUuid = $stock->getLocationUuid();
            // $locationCode = ... (ถ้าต้องการ Code ต้อง query เพิ่ม แต่ UseCase ของ Logistics ใช้แค่ ID ได้)

            $suggestions[] = new \TmrEcosystem\Stock\Application\DTOs\StockPickingSuggestionDto(
                locationId: $locationUuid,
                quantity: $take,
                batchId: null // รองรับ Batch ในอนาคต
            );

            $qtyRemaining -= $take;
        }

        // กรณีของไม่พอ (ถ้าต้องการแจ้งเตือน Backorder ให้จัดการที่ UseCase ปลายทาง)

        return $suggestions;
    }

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
