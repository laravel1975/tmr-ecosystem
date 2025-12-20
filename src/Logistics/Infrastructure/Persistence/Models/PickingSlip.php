<?php

namespace TmrEcosystem\Logistics\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use TmrEcosystem\IAM\Domain\Models\User;

class PickingSlip extends Model
{
    use HasUuids;

    protected $table = 'logistics_picking_slips';

    protected $guarded = [];

    protected $casts = [
        'picked_at' => 'datetime',
    ];

    /**
     * ✅ REFACTOR: Instead of a hard relationship, we treat order_id as a plain reference.
     * If you strictly need data from Sales, fetch it via a separate Query Service or Read Model.
     * * But if you absolutely need the relationship for Inertia views (lazy loading),
     * define it in a specific "Read Model" class, not this Write Model.
     */
    // public function order()
    // {
    //     return $this->belongsTo(SalesOrderModel::class, 'order_id');
    // }

    /**
     * Helper to get the reference ID explicitly
     */
    public function getOrderId(): string
    {
        return $this->order_id;
    }

    public function picker()
    {
        return $this->belongsTo(User::class, 'picker_user_id');
    }

    public function items()
    {
        return $this->hasMany(PickingSlipItem::class, 'picking_slip_id');
    }
}
