<?php

namespace TmrEcosystem\HRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class LeaveType extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'is_paid',
        'max_days_per_year',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'max_days_per_year' => 'decimal:2',
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

    public function leaveRequests()
    {
        // (ประเภทการลานี้ ถูกใช้ในใบลากี่ใบ)
        return $this->hasMany(LeaveRequest::class);
    }
}
