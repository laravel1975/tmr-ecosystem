<?php

namespace TmrEcosystem\Maintenance\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

/**
 * @property int $id
 * @property string $name (เช่น "Inspection", "Replacement", "Lubrication")
 * @property string $code
 * @property int $company_id
 */
class ActivityType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'company_id',
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
}
