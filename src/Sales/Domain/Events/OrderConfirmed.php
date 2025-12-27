<?php

namespace TmrEcosystem\Sales\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;

class OrderConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $orderId,

        // ✅ [Fix] ทำให้เป็น Nullable และ Default null เพื่อแก้ Error "Expected 2 arguments. Found 1."
        public ?OrderSnapshotDto $orderSnapshot = null
    ) {}
}
