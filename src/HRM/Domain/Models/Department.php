<?php

namespace TmrEcosystem\HRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class Department extends Model
{
    protected $fillable = ['company_id', 'name', 'parent_id', 'description'];

    // (สำคัญ) ใช้ Global Scope ของคุณ
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function employees()
    {
        return $this->hasMany(EmployeeProfile::class);
    }
}
