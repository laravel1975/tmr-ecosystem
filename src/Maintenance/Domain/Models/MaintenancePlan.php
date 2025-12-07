<?php

namespace TmrEcosystem\Maintenance\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class MaintenancePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'asset_id',
        'maintenance_type_id',
        'company_id',
        'status', // (active, inactive)

        // (ประเภทการ Trigger: TIME, METER, EVENT)
        'trigger_type',

        // (สำหรับ Time-Based)
        'interval_days',
        'next_due_date', // (วันที่ PM ครั้งถัดไป)

        // (สำหรับ Meter-Based - เราจะทำทีหลัง)
        // 'interval_meter',
        // 'last_meter_reading',
    ];

    protected $casts = [
        'next_due_date' => 'date',
        'interval_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    /**
     * ความสัมพันธ์: รายการ Checklist ของแผน PM นี้
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenancePlanTask::class)->orderBy('sort_order', 'asc');
    }
}
