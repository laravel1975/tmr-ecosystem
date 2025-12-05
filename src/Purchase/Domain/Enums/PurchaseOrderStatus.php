<?php

namespace TmrEcosystem\Purchase\Domain\Enums;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case ORDERED = 'ordered';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';
}
