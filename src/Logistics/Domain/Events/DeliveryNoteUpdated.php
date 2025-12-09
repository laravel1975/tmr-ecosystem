<?php

namespace TmrEcosystem\Logistics\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;

class DeliveryNoteUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public DeliveryNote $deliveryNote
    ) {}
}
