<?php

namespace TmrEcosystem\Maintenance\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

/**
 * @property int $id
 * @property string $name (ชื่อบริษัทผู้รับเหมา)
 * @property string|null $contact_person (ชื่อผู้ติดต่อ)
 * @property string|null $phone
 * @property string|null $email
 * @property int $company_id
 */
class Contractor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_id',
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
     * (3. [ใหม่] เพิ่ม Relation นี้)
     * (ประวัติการถูกมอบหมายงานซ่อมทั้งหมด)
     */
    public function maintenanceAssignments(): MorphMany
    {
        return $this->morphMany(MaintenanceAssignment::class, 'assignable');
    }
}
