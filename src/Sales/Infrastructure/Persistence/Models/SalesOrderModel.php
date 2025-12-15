<?php

namespace TmrEcosystem\Sales\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\ReturnNote;

class SalesOrderModel extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sales_orders';
    protected $keyType = 'string';
    public $incrementing = false;

    // ป้องกัน Mass Assignment แต่ระวังตอนใช้นะครับ
    protected $guarded = [];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // ความสัมพันธ์กับ Items
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItemModel::class, 'order_id');
    }

    // ✅ [เพิ่ม] Relationship
    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    // ✅ [เพิ่ม] ความสัมพันธ์กับ Customer
    public function customer(): BelongsTo
    {
        // customer_id ใน sales_orders เชื่อมกับ id ใน customers
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function isBackorder(): bool
    {
        return $this->stock_status === 'backorder';
    }

    // ✅ [เพิ่ม] ความสัมพันธ์กับ Picking Slips
    public function pickingSlips()
    {
        return $this->hasMany(PickingSlip::class, 'order_id');
    }

    // ✅ [เพิ่ม] ความสัมพันธ์กับ Delivery Notes
    public function deliveryNotes()
    {
        return $this->hasMany(DeliveryNote::class, 'order_id');
    }

    // ✅ [เพิ่ม] ความสัมพันธ์กับ Return Notes
    public function returnNotes()
    {
        return $this->hasMany(ReturnNote::class, 'order_id');
    }
}
