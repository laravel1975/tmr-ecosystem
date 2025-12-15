<?php

namespace TmrEcosystem\Logistics\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;

class PickingSlipUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PickingSlip $pickingSlip
    ) {}
}
