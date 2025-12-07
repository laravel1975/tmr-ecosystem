<?php

namespace TmrEcosystem\Maintenance\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

/**
 * นี่คือ "สำเนา" ข้อมูล Technician จาก HRM Bounded Context
 * ใช้สำหรับอ่าน (Read-Only) ใน Maintenance BC
 */
class MaintenanceTechnician extends Model
{
    use HasFactory;

    protected $primaryKey = 'employee_profile_id'; // (ระบุ PK ที่ไม่ใช่ id)
    public $incrementing = false; // (PK นี้ไม่ใช่ Auto-increment)

    protected $fillable = [
        'employee_profile_id',
        'company_id',
        'first_name',
        'last_name',
        'hourly_rate',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * (2. [ใหม่] เพิ่ม Relation นี้)
     * (ประวัติการถูกมอบหมายงานซ่อมทั้งหมด)
     */
    public function maintenanceAssignments(): MorphMany
    {
        // (เราต้องระบุ 'assignable_id' และ 'assignable_type'
        //  และใช้ 'employee_profile_id' เป็น Local Key)
        return $this->morphMany(
            MaintenanceAssignment::class,
            'assignable',
            'assignable_type',
            'assignable_id',
            'employee_profile_id'
        );
    }
}
