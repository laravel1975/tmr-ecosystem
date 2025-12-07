<?php

namespace TmrEcosystem\HRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\IAM\Domain\Models\User; // (IAM Context)
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class Attendance extends Model
{
    protected $fillable = [
        'company_id',
        'employee_profile_id',
        'work_shift_id',
        'date',
        'clock_in',
        'clock_out',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_latitude',
        'clock_out_longitude',
        'total_work_hours',
        'status',
        'source',
        'notes',
        'adjusted_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'total_work_hours' => 'decimal:2',
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

    public function employeeProfile()
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function workShift()
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function adjuster()
    {
        // (ผู้แก้ไข)
        return $this->belongsTo(User::class, 'adjusted_by_user_id');
    }

    public function overtimeRequests()
    {
        // (การลงเวลานี้ มีการขอ OT กี่ครั้ง)
        return $this->hasMany(OvertimeRequest::class);
    }
}
