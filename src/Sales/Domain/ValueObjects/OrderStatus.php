<?php

namespace TmrEcosystem\Sales\Domain\ValueObjects;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingReservation = 'pending_reservation'; // รอจอง (Optional: ใช้ถ้าอยากแยกสถานะก่อน Soft Reserve)
    case Confirmed = 'confirmed';
    case PartiallyShipped = 'partially_shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold'; // ✅ Added: สำหรับเคสที่ระบบมีปัญหา (เช่น จองหลุด) ต้องการคนมาตรวจสอบ

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::PendingReservation => 'Pending Reservation',
            self::Confirmed => 'Confirmed',
            self::PartiallyShipped => 'Partially Shipped',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::OnHold => 'On Hold', // ✅ Added Label
        };
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }
}
