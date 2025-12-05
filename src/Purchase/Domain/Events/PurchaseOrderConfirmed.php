<?php

namespace TmrEcosystem\Purchase\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TmrEcosystem\Purchase\Domain\Models\PurchaseOrder;

class PurchaseOrderConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder) {}
}
