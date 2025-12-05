<?php

namespace TmrEcosystem\Purchase\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\Item;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'quantity',
        'unit_price',
        'total_price'
    ];

    // Relation to Inventory Item (Assuming Inventory Module exists as per context)
    public function item()
    {
        // parameter: RelatedModel, foreign_key, owner_key
        return $this->belongsTo(Item::class, 'item_id', 'uuid');
    }
}
