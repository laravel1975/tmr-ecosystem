<?php

namespace TmrEcosystem\Sales\Domain\ValueObjects;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingReservation = 'pending_reservation';
    case Confirmed = 'confirmed';
    case PartiallyShipped = 'partially_shipped'; // ✅ เพิ่มสถานะนี้
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::PendingReservation => 'Pending Reservation',
            self::Confirmed => 'Confirmed',
            self::PartiallyShipped => 'Partially Shipped',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
