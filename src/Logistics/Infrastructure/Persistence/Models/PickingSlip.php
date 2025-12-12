<?php

namespace TmrEcosystem\Logistics\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use TmrEcosystem\IAM\Domain\Models\User;
// Import Model ข้าม Module (Sales)
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class PickingSlip extends Model
{
    use HasUuids;

    protected $table = 'logistics_picking_slips';

    protected $guarded = [];

    protected $casts = [
        'picked_at' => 'datetime',
    ];

    /**
     * ความสัมพันธ์กลับไปหา Sales Order
     */
    public function order()
    {
        return $this->belongsTo(SalesOrderModel::class, 'order_id');
    }

    /**
     * (Optional) ความสัมพันธ์กับคนหยิบของ (User)
     * ใช้ App\Models\User หรือ IAM Module ตามที่คุณมี
     */
    public function picker()
    {
        return $this->belongsTo(User::class, 'picker_user_id');
    }

    public function items()
    {
        return $this->hasMany(PickingSlipItem::class, 'picking_slip_id');
    }
}
