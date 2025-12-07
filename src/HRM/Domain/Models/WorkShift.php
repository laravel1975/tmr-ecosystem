<?php

namespace TmrEcosystem\HRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class WorkShift extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'work_hours_per_day',
        'is_flexible',
    ];

    protected $casts = [
        'is_flexible' => 'boolean',
        'start_time' => 'datetime:H:i', // (Casting เป็น H:i)
        'end_time' => 'datetime:H:i',
        'work_hours_per_day' => 'decimal:2',
    ];

    // (ใช้ Global Scope)
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    // --- Relationships ---

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeProfiles()
    {
        // (กะทำงานนี้ ถูกใช้โดยพนักงานคนไหนบ้าง)
        return $this->hasMany(EmployeeProfile::class);
    }
}
