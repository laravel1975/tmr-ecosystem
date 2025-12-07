<?php

namespace TmrEcosystem\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TmrEcosystem\Shared\Infrastructure\Persistence\Traits\BelongsToCompany;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class ProductionOrder extends Model
{
    use SoftDeletes, BelongsToCompany;

    protected $table = 'manufacturing_production_orders';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'produced_quantity' => 'decimal:4',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
    ];

    // สินค้าที่จะผลิต
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'item_uuid', 'uuid');
    }

    // สูตรที่ใช้
    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_uuid', 'uuid');
    }

    // (Optional) ถ้าต้องการดึงข้อมูล Sales Order
    public function salesOrder()
    {
        return $this->belongsTo(SalesOrderModel::class, 'origin_uuid', 'uuid');
    }
}
