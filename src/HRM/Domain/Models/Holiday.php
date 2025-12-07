<?php

namespace TmrEcosystem\HRM\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class Holiday extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'date',
        'is_recurring',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_recurring' => 'boolean',
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
}
