<?php

namespace TmrEcosystem\Maintenance\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int|null $parent_id (สำหรับ Hierarchy)
 * @property int $company_id
 */
class FailureCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'parent_id', // (ID ของ FailureCode แม่)
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

    /**
     * ความสัมพันธ์: Parent (แม่)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * ความสัมพันธ์: Children (ลูก)
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
