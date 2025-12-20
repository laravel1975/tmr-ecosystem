<?php

namespace TmrEcosystem\Logistics\Application\UseCases;

use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Stock\Application\Services\StockReservationService; // Cross-boundary usage allowed via Interface in real DDD
use Illuminate\Support\Facades\DB;

class CreatePickingSlipUseCase
{
    // ... Dependencies ...

    public function createFromOrder($orderSnapshot)
    {
        // 1. Create Picking Slip (Status: Pending Allocation)
        $slip = PickingSlip::create([...]);

        // 2. Request Allocation Strategy from Inventory BC
        // (Inventory BC จะบอกว่าควรหยิบจาก Location ไหนที่มี Hard Reserve หรือ Soft Reserve)

        // 3. Save Picking Items
    }
}
