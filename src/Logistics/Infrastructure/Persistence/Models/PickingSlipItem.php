<?php

namespace TmrEcosystem\Logistics\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderItemModel;

class PickingSlipItem extends Model
{
    protected $table = 'logistics_picking_slip_items';
    protected $guarded = [];

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItemModel::class, 'sales_order_item_id');
    }
}
