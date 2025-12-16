<?php

namespace TmrEcosystem\Sales\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;

class OrderConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        // เรายังเก็บ orderId ไว้แบบ Public Property เพื่อ Backward Compatibility (เผื่อ Consumer เก่ายังใช้)
        public string $orderId,

        // [New] ข้อมูล Snapshot สำหรับ Logistics (Rich Payload)
        public OrderSnapshotDto $orderSnapshot
    ) {}
}
