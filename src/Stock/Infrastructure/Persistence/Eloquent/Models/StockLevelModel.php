<?php

namespace TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class StockLevelModel extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * ชื่อตาราง
     */
    protected $table = 'stock_levels';

    /**
     * $fillable: Fields ที่อนุญาตให้กรอก (ต้องตรงกับ Migration)
     */
    protected $fillable = [
        'uuid',
        'company_id',
        'item_uuid',
        'warehouse_uuid',
        'location_uuid',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_soft_reserved'
    ];

    /**
     * ใช้ Global Scope (Multi-Tenancy)
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * $casts: แปลง Types
     * (อิงตาม Migration ที่เราใช้ decimal 15, 4)
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'quantity_soft_reserved' => 'decimal:4',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * ระบุคอลัมน์ UUID (Migration ของเราใช้ 'uuid')
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Relationships (ภายใน Bounded Context)
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * (Relationship ที่เชื่อมไปยัง StockMovements)
     */
    public function movements()
    {
        return $this->hasMany(StockMovementModel::class, 'stock_level_uuid', 'uuid');
    }

    /**
     * (เราจะไม่สร้าง Relationship ไปยัง Item หรือ Warehouse ที่นี่
     * เพราะมันอยู่คนละ Bounded Context)
     */
}
