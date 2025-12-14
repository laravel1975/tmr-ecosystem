<?php

namespace TmrEcosystem\Stock\Application\UseCases;

use TmrEcosystem\Stock\Application\DTOs\ReceiveStockData;
use TmrEcosystem\Stock\Domain\Aggregates\StockLevel;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Domain\Services\LocationCapacityChecker;
use Exception;
use Illuminate\Support\Str;

class ReceiveStockUseCase
{
    public function __construct(
        protected StockLevelRepositoryInterface $stockRepository,
        private LocationCapacityChecker $capacityChecker // ✅ Inject Service ตรวจสอบความจุ
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(ReceiveStockData $data): void
    {
        if ($data->quantity <= 0) {
            throw new Exception("Quantity to receive must be positive.");
        }

        // 1. ✅ [NEW] ตรวจสอบความจุของ Location ก่อนรับของ (Capacity Check)
        // ถ้าเต็ม Service นี้จะ Throw Exception ออกมาเอง
        $this->capacityChecker->check(
            $data->locationUuid,
            $data->quantity,
            $data->companyId
        );

        // 2. ค้นหา StockLevel ใน Location นั้น (Specific Location)
        $stockLevel = $this->stockRepository->findByLocation(
            $data->itemUuid,
            $data->locationUuid,
            $data->companyId
        );

        // 3. ถ้ายังไม่มี Stock ใน Location นี้ -> สร้าง Aggregate Root ใหม่
        if (is_null($stockLevel)) {
            $stockLevel = StockLevel::create(
                uuid: (string) Str::uuid(),
                companyId: $data->companyId,
                itemUuid: $data->itemUuid,
                warehouseUuid: $data->warehouseUuid,
                locationUuid: $data->locationUuid
            );
        }

        // 4. ทำรายการรับเข้า (Domain Logic)
        $movement = $stockLevel->receive(
            quantityToReceive: $data->quantity,
            userId: $data->userId,
            reference: $data->reference
        );

        // 5. บันทึกข้อมูลลง Database
        $this->stockRepository->save($stockLevel, [$movement]);
    }
}
