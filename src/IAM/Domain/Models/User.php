<?php

namespace TmrEcosystem\IAM\Domain\Models;

use App\Models\Company;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory; // <-- 1. Import HasFactory (มีอยู่แล้ว)
use Database\Factories\UserFactory; // <--- 2. (สำคัญ) Import UserFactory ของเรา
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

// --- 1. Import ---
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use TmrEcosystem\HRM\Domain\Models\EmployeeProfile;

class User extends Authenticatable implements MustVerifyEmail
{
    // --- 2. เพิ่ม Trait ---
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'phone',
        'avatar_url',
        'is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array', // <-- (แก้ไขจาก Error)
        ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * (เพิ่มฟังก์ชันนี้เข้าไปในคลาส)
     */
    // --- 4. (สำคัญ) เพิ่มเมธอดนี้ ---
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * ดึงข้อมูลโปรไฟล์พนักงาน (HRM) ที่ผูกกับ User นี้
     * (One-to-One)
     */
    public function profile()
    {
        // (เราต้องระบุ 'user_id' เพราะชื่อ Method 'profile'
        // ไม่ตรงกับ 'employeeProfile' ที่ Laravel คาดเดา)
        return $this->hasOne(EmployeeProfile::class, 'user_id');
    }

    // --- 3. เพิ่มฟังก์ชันนี้ (สำหรับตั้งค่า Log) ---
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // 3.1 บันทึกเฉพาะ field ที่มีการเปลี่ยนแปลง
            ->logOnlyDirty()
            // 3.2 ไม่ต้องบันทึก field เหล่านี้
            ->dontLogIfAttributesChangedOnly(['password', 'remember_token', 'updated_at'])
            // 3.3 บันทึก field เหล่านี้ (เกือบทั้งหมด)
            ->logFillable()
            // 3.4 คำอธิบาย Log
            ->setDescriptionForEvent(fn(string $eventName) => "User profile [{$this->email}] has been {$eventName}");
    }



    /**
     * Get the employee profile associated with the user.
     */
    public function employeeProfile(): HasOne
    {
        // Assuming 'user_id' is the foreign key in employee_profiles table
        return $this->hasOne(EmployeeProfile::class, 'user_id');
    }
}
