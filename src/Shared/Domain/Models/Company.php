<?php

namespace TmrEcosystem\Shared\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use TmrEcosystem\IAM\Domain\Models\User;

// --- 1. Import ---
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use TmrEcosystem\HRM\Domain\Models\Department;
use TmrEcosystem\HRM\Domain\Models\EmployeeProfile;
use TmrEcosystem\HRM\Domain\Models\Position;

/**
 * @mixin IdeHelperCompany
 */
class Company extends Model
{
    // --- 2. เพิ่ม Trait ---
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        // ข้อมูลพื้นฐาน
        'name',
        'slug',
        'registration_no',
        'description',

        // โลโก้และโปรไฟล์
        'logo',
        'company_profile',

        // การติดต่อ
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',

        // เจ้าของ
        'owner_id',

        // การตั้งค่าบริษัทเพิ่มเติม
        'settings',

        // สถานะ
        'is_active',
        'verified_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * --- 3. (สำคัญที่สุด) เพิ่มเมธอดนี้ ---
     * (นี่คือการบอก HasFactory trait ว่า Factory ของเราอยู่ที่ไหน)
     * (ใช้รูปแบบเดียวกับ User.php และ Asset.php ของคุณ)
     */
    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    // ความสัมพันธ์กับผู้ใช้เจ้าของ
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // ความสัมพันธ์กับผู้ใช้พนักงาน
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function employeeProfiles()
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    // --- 3. เพิ่มฟังก์ชันนี้ (สำหรับตั้งค่า Log) ---
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->logFillable()
            ->setDescriptionForEvent(fn(string $eventName) => "Company [{$this->name}] has been {$eventName}");
    }
}
