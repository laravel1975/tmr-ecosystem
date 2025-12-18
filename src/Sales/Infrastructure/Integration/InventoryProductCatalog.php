<?php

namespace TmrEcosystem\Sales\Infrastructure\Integration;

use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Services\ProductCatalogInterface;
use TmrEcosystem\Sales\Domain\Services\ProductData;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Contracts\StockCheckServiceInterface;

class InventoryProductCatalog implements ProductCatalogInterface
{
    // กำหนด Warehouse เริ่มต้นสำหรับการแสดงผลใน Catalog (อาจย้ายไป Config ในอนาคต)
    private const DEFAULT_CATALOG_WAREHOUSE = 'DEFAULT_WAREHOUSE';

    public function __construct(
        private ItemLookupServiceInterface $inventoryService,
        private StockCheckServiceInterface $stockService
    ) {}

    public function findProduct(string $productId): ?ProductData
    {
        // 1. ค้นหาข้อมูลสินค้าจาก Inventory (ในที่นี้ $productId คือ Part Number ตาม Logic เดิม)
        $dto = $this->inventoryService->findByPartNumber($productId);

        if (!$dto) {
            return null;
        }

        // 2. ดึงข้อมูล Stock จริงจาก Stock Module
        // ใช้ Part Number หรือ UUID ในการเช็ค (แนะนำให้ใช้ UUID ถ้า DTO มีมาให้)
        $stockAvailable = 0;
        try {
            // ถ้า DTO มี id (UUID) ให้ใช้ getAvailableQuantity (เร็วกว่า)
            if (isset($dto->id)) {
                $stockAvailable = $this->stockService->getAvailableQuantity(
                    $dto->id,
                    self::DEFAULT_CATALOG_WAREHOUSE
                );
            } else {
                // ถ้าไม่มี UUID ให้ใช้ Part Number
                $stockAvailable = $this->stockService->checkAvailability(
                    $dto->partNumber,
                    self::DEFAULT_CATALOG_WAREHOUSE
                );
            }
        } catch (\Exception $e) {
            Log::warning("Could not fetch stock for product {$productId}: " . $e->getMessage());
            $stockAvailable = 0; // Fallback ปลอดภัยไว้ก่อน
        }

        // 3. ประกอบ Object ส่งกลับให้ Sales Domain
        return new ProductData(
            id: $dto->partNumber, // ยังคง Mapping ID เป็น PartNumber ตามเดิมเพื่อความเข้ากันได้
            name: $dto->name,
            price: (float) $dto->price, // แปลง Type ให้ชัวร์
            stockAvailable: (int) $stockAvailable, // ✅ ใช้ค่าจริงแทน 999
            imageUrl: $dto->imageUrl ?? null
        );
    }

    public function getProductsByIds(array $productIds): array
    {
        // 1. ดึงข้อมูลสินค้าแบบ Batch
        $dtos = $this->inventoryService->getByPartNumbers($productIds);

        if (empty($dtos)) {
            return [];
        }

        // 2. ดึงข้อมูล Stock แบบ Batch (เพื่อ Performance)
        // สร้างรายการ Part Numbers ที่พบจริง
        $foundPartNumbers = array_keys($dtos);
        $stocks = [];

        try {
            $stocks = $this->stockService->checkAvailabilityBatch(
                $foundPartNumbers,
                self::DEFAULT_CATALOG_WAREHOUSE
            );
        } catch (\Exception $e) {
            Log::warning("Could not fetch batch stock: " . $e->getMessage());
            // กรณี Error ให้ $stocks เป็น empty array
        }

        // 3. Map ข้อมูลกลับ
        $result = [];
        foreach ($dtos as $partNumber => $dto) {
            $available = $stocks[$partNumber] ?? 0;

            $result[$partNumber] = new ProductData(
                id: $dto->partNumber,
                name: $dto->name,
                price: (float) $dto->price,
                stockAvailable: (int) $available, // ✅ ใช้ค่าจริง
                imageUrl: $dto->imageUrl ?? null
            );
        }

        return $result;
    }
}
