<?php

namespace TmrEcosystem\Logistics\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class DeliveryNote extends Model
{
    use HasUuids;

    protected $table = 'logistics_delivery_notes';

    protected $guarded = [];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // ✅ [เพิ่ม] Auto-generate Token
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->tracking_token)) {
                $model->tracking_token = Str::random(32);
            }
        });
    }

    // Accessor (Optional, แต่มีไว้ก็ดี)
    public function getPublicTrackingUrlAttribute()
    {
        return route('public.track', ['token' => $this->tracking_token]);
    }

    // ความสัมพันธ์กลับไปหา Order
    public function order()
    {
        return $this->belongsTo(SalesOrderModel::class, 'order_id');
    }

    // ความสัมพันธ์กับ Picking Slip
    public function pickingSlip()
    {
        return $this->belongsTo(PickingSlip::class, 'picking_slip_id');
    }

    // ความสัมพันธ์กับ Shipment (Trip)
    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    // ✅ [เพิ่มใหม่] Helper Relation: ดึงรายการสินค้า "เฉพาะที่อยู่ในใบส่งของรอบนี้"
    // โดยการวิ่งผ่าน PickingSlip ไปหา PickingSlipItems
    // ช่วยให้ Frontend แสดงรายการของ Delivery Note นี้ได้ถูกต้องตามหลัก 1:N
    public function items()
    {
        return $this->hasManyThrough(
            PickingSlipItem::class, // ปลายทาง (Items)
            PickingSlip::class,     // ตัวกลาง (Picking Slip)
            'id',                   // PK ของ PickingSlip (เชื่อมกับ picking_slip_id ใน DeliveryNote)
            'picking_slip_id',      // FK ใน PickingSlipItems (เชื่อมกับ PickingSlip)
            'picking_slip_id',      // Local Key ใน DeliveryNote (FK ที่ชี้ไป PickingSlip)
            'id'                    // Local Key ใน PickingSlip
        );
    }
}
