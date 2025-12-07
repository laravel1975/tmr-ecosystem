<?php

namespace TmrEcosystem\Maintenance\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class SparePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'part_number',
        'description',
        'stock_quantity',    // (คงไว้)
        'unit_cost',         // (คงไว้)
        'reorder_level',     // (คงไว้)
        'location',          // (คงไว้)
        'company_id',
        'item_uuid',     // (👈 เพิ่มบรรทัดนี้)
    ];

    protected $casts = [
        'stock_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'reorder_level' => 'integer',
    ];

    /**
     * (สำคัญ) ใช้ Global Scope ของคุณ
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * ความสัมพันธ์: ประวัติการถูกใช้งานใน Work Order ทั้งหมด
     */
    public function workOrderUsages(): HasMany
    {
        return $this->hasMany(WorkOrderSparePart::class);
    }
}
