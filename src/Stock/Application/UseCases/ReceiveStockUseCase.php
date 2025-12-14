<?php

namespace TmrEcosystem\Stock\Application\UseCases;

use TmrEcosystem\Stock\Application\DTOs\ReceiveStockData;
use TmrEcosystem\Stock\Domain\Aggregates\StockLevel;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Domain\Services\LocationCapacityChecker;
use TmrEcosystem\Stock\Domain\Events\StockReceived; // ✅ Import Event
use Exception;
use Illuminate\Support\Str;

class ReceiveStockUseCase
{
    public function __construct(
        protected StockLevelRepositoryInterface $stockRepository,
        private LocationCapacityChecker $capacityChecker
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(ReceiveStockData $data): void
    {
        if ($data->quantity <= 0) {
            throw new Exception("Quantity to receive must be positive.");
        }

        // 1. ตรวจสอบความจุของ Location
        $this->capacityChecker->check(
            $data->locationUuid,
            $data->quantity,
            $data->companyId
        );

        // 2. ค้นหา StockLevel เดิม
        $stockLevel = $this->stockRepository->findByLocation(
            $data->itemUuid,
            $data->locationUuid,
            $data->companyId
        );

        // 3. ถ้าไม่มี สร้างใหม่
        if (is_null($stockLevel)) {
            $stockLevel = StockLevel::create(
                uuid: (string) Str::uuid(),
                companyId: $data->companyId,
                itemUuid: $data->itemUuid,
                warehouseUuid: $data->warehouseUuid,
                locationUuid: $data->locationUuid
            );
        }

        // 4. ทำรายการรับเข้า
        $movement = $stockLevel->receive(
            quantityToReceive: $data->quantity,
            userId: $data->userId,
            reference: $data->reference
        );

        // 5. บันทึกข้อมูล
        $this->stockRepository->save($stockLevel, [$movement]);

        // 6. ✅ [NEW] ประกาศ Event ว่ามีของเข้ามาแล้ว
        // เพื่อให้ Listener (AllocateBackorders) ทำงานต่อ
        event(new StockReceived(
            itemUuid: $data->itemUuid,
            locationUuid: $data->locationUuid,
            quantity: $data->quantity,
            companyId: $data->companyId
        ));
    }
}
