<?php

namespace TmrEcosystem\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;

class BillOfMaterialByproduct extends Model
{
    protected $table = 'manufacturing_bom_byproducts';
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'item_uuid', 'uuid');
    }
}
