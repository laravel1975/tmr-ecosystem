<?php

namespace TmrEcosystem\Shared\Domain\Enums;

enum ReservationState: string
{
    case NONE = 'none';
    case SOFT_RESERVED = 'soft_reserved';   // จองชั่วคราว มีวันหมดอายุ (Draft)
    case HARD_RESERVED = 'hard_reserved';   // จองถาวร ตัดยอดขายแล้ว (Confirmed)
    case PICKING = 'picking';               // กำลังหยิบ (Warehouse Process)
    case FULFILLED = 'fulfilled';           // ส่งของแล้ว (Shipment)
    case RELEASED = 'released';             // ยกเลิกการจอง คืนของเข้ากองกลาง
    case EXPIRED = 'expired';               // หมดอายุ (System Auto-release)
}
