<?php

namespace TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StockReservationModel extends Model
{
    use HasUuids;

    protected $table = 'inventory_stock_reservations';

    protected $fillable = [
        'id',
        'inventory_item_id',
        'warehouse_id',
        'reference_id',
        'quantity',
        'state',
        'expires_at',
    ];

    protected $casts = [
        'quantity' => 'float',
        'expires_at' => 'datetime',
    ];
}
