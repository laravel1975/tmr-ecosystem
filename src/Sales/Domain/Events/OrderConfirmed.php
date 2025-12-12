<?php

namespace TmrEcosystem\Sales\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     * เราส่งแค่ ID เพื่อลดขนาด Message และป้องกัน Stale Data
     */
    public function __construct(
        public string $orderId
    ) {}
}
