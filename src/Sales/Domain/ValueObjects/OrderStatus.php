<?php

namespace TmrEcosystem\Sales\Domain\ValueObjects;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingReservation = 'pending_reservation';
    case Confirmed = 'confirmed';
    // ✅ [เพิ่ม] สถานะสำหรับการส่งของบางส่วน
    case PartiallyShipped = 'partially_shipped';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::PendingReservation => 'Waiting for Stock',
            self::Confirmed => 'Confirmed',
            // ✅ [เพิ่ม] Label สำหรับแสดงผล
            self::PartiallyShipped => 'Partially Shipped',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }
}
