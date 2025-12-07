<?php

namespace TmrEcosystem\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TmrEcosystem\Shared\Infrastructure\Persistence\Traits\BelongsToCompany;
// ✅ Import Item จาก Inventory ที่ถูกต้อง
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;

class BillOfMaterial extends Model
{
    use SoftDeletes, BelongsToCompany;

    protected $table = 'manufacturing_boms';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'output_quantity' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'item_uuid', 'uuid');
    }

    public function components(): HasMany
    {
        return $this->hasMany(BillOfMaterialComponent::class, 'bom_uuid', 'uuid');
    }
}
