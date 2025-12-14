<?php

namespace TmrEcosystem\Stock\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $itemUuid รหัสสินค้า (UUID)
     * @param string $locationUuid ตำแหน่งที่รับของเข้า
     * @param float $quantity จำนวนที่รับเข้า
     * @param string $companyId รหัสบริษัท
     */
    public function __construct(
        public string $itemUuid,
        public string $locationUuid,
        public float $quantity,
        public string $companyId
    ) {}
}
