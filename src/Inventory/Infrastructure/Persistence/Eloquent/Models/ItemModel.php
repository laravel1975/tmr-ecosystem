<?php

namespace TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Database\Factories\ItemFactory;
use TmrEcosystem\Shared\Infrastructure\Persistence\Casts\MoneyCast;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class ItemModel extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'inventory_items';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    // ✅ เปลี่ยนจาก category, uom เป็น category_id, uom_id
    protected $fillable = [
        'uuid',
        'company_id',
        'part_number',
        'name',
        'description',
        'category_id', // New FK
        'average_cost',
        'uom_id',      // New FK
        'cost_price',   // ✅ เพิ่ม (ใช้ MoneyCast)
        'sale_price',   // ✅ เพิ่ม (ใช้ MoneyCast)
        'image_path',
        'tracking_strategy'
    ];

    protected function casts(): array
    {
        return [
            'average_cost' => 'decimal:4',
            'cost_price' => MoneyCast::class, // แปลง int <-> Money
            'sale_price' => MoneyCast::class, // แปลง int <-> Money
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ✅ Relationships ใหม่
    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function uom()
    {
        return $this->belongsTo(InventoryUom::class, 'uom_id');
    }

    // ✅ เพิ่มความสัมพันธ์ Images
    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class, 'item_uuid', 'uuid')->orderBy('sort_order');
    }

    // ✅ Helper: ดึงรูปหลัก (ถ้าไม่มีให้เอารูปแรก)
    public function getPrimaryImageAttribute()
    {
        return $this->images->where('is_primary', true)->first()
            ?? $this->images->first();
    }
}
