<?php

namespace TmrEcosystem\HRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\IAM\Domain\Models\User; // (IAM Context)
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class LeaveRequest extends Model
{
    protected $fillable = [
        'company_id',
        'employee_profile_id',
        'leave_type_id',
        'start_datetime',
        'end_datetime',
        'total_days',
        'reason',
        'status',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'approved_at' => 'datetime',
        'total_days' => 'decimal:2',
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

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver()
    {
        // (ผู้อนุมัติ)
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
