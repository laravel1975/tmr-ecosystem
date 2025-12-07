<?php

namespace TmrEcosystem\HRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_profile_id',
        'company_id',
        'uploaded_by_user_id',
        'title',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'file_size' => 'integer',
    ];

    // (สำคัญ) ใช้ Global Scope ของคุณ
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function employeeProfile()
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
