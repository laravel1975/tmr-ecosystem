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
        'generated_at' => 'datetime'
    ];

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
