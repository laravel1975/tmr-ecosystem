<?php

namespace TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // (สำหรับจัดการ 'uuid')
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

// (เราจะสร้าง Factory นี้ในอนาคต)
// use TmrEcosystem\Warehouse\Infrastructure\Persistence\Database\Factories\WarehouseFactory;

class WarehouseModel extends Model
{
    // (1) ใช้ Traits ที่จำเป็น
    use HasFactory, SoftDeletes, HasUuids;

    // (2) ระบุชื่อตาราง
    protected $table = 'warehouses';

    /**
     * (3) $fillable: Fields ที่อนุญาตให้กรอก (ต้องตรงกับ Migration)
     */
    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    /**
     * (4) ใช้ Global Scope (เหมือน ItemModel)
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * (5) $casts: แปลง Types
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * (6) ระบุคอลัมน์ UUID (Migration ของเราใช้ 'uuid')
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * (7) Relationships (เหมือน ItemModel)
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * (8) (ในอนาคต) เชื่อมโยง Factory
     */
    // protected static function newFactory(): WarehouseFactory
    // {
    //     return WarehouseFactory::new();
    // }
}
