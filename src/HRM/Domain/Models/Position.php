<?php

namespace TmrEcosystem\HRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class Position extends Model
{
    protected $fillable = ['title',
        'description',
        'department_id',
        'company_id',
    ];

    // (สำคัญ) ใช้ Global Scope ของคุณ
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(EmployeeProfile::class);
    }
}
