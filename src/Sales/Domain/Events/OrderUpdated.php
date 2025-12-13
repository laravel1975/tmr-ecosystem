<?php

namespace TmrEcosystem\Sales\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param string|object $orderId  รับได้ทั้ง ID string หรือ Order Object
     */
    public function __construct(
        public $orderId
    ) {}
}
