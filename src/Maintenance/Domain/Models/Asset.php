<?php

namespace TmrEcosystem\Maintenance\Domain\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;
use TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'asset_code',
        'description',
        'location',          // (ยังคงไว้สำหรับ ACL)
        'model_number',
        'serial_number',
        'purchase_date',
        'warranty_end_date',
        'status',
        'company_id',
        'warehouse_uuid', // (คอลัมน์ใหม่)
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_end_date' => 'date',
    ];

    /**
     * (สำคัญ) ใช้ Global Scope ของคุณ
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * * สร้าง Factory instance สำหรับโมเดลนี้
     */
    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * ความสัมพันธ์: ประวัติใบสั่งซ่อมทั้งหมดของ Asset นี้
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * ความสัมพันธ์: ประวัติการแจ้งซ่อมทั้งหมดของ Asset นี้
     */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    /**
     * (2. 👈 [ใหม่] Relation ข้าม Bounded Context)
     * (เชื่อม 'warehouse_uuid' (Local) ไปยัง 'uuid' (Foreign) ของ WarehouseModel)
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_uuid', 'uuid');
    }
}
