<?php

namespace TmrEcosystem\Sales\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // 1. Import Trait

class SalesOrderItemModel extends Model
{
    use HasUuids; // 2. ใช้งาน Trait UUID

    protected $table = 'sales_order_items';

    // 3. Config ให้ Eloquent รู้ว่า PK ไม่ใช่ Auto Increment
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrderModel::class, 'order_id');
    }
}
