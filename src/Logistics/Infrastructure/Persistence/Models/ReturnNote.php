<?php

namespace TmrEcosystem\Logistics\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany; // ✅ Import
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class ReturnNote extends Model
{
    use HasUuids;

    protected $table = 'logistics_return_notes';
    protected $guarded = [];

    protected $fillable = [
        'return_number',
        'order_id',
        'picking_slip_id',
        'status',
        'reason',
        'evidence_image_path',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ... (Relationships เดิม items, order, pickingSlip) ...

    public function items()
    {
        return $this->hasMany(ReturnNoteItem::class, 'return_note_id');
    }

    public function order()
    {
        return $this->belongsTo(SalesOrderModel::class, 'order_id');
    }

    public function pickingSlip()
    {
        return $this->belongsTo(PickingSlip::class, 'picking_slip_id');
    }

    // ✅ เพิ่ม Relationship ใหม่
    public function evidenceImages(): HasMany
    {
        return $this->hasMany(ReturnEvidence::class, 'return_note_id');
    }

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class, 'delivery_note_id');
    }
}
