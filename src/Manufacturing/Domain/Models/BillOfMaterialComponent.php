<?php

namespace TmrEcosystem\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;

class BillOfMaterialComponent extends Model
{
    protected $table = 'manufacturing_bom_components';
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:4',
        'waste_percent' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'component_item_uuid', 'uuid');
    }
}
