<?php

namespace TmrEcosystem\HRM\Domain\Models;

use App\Models\Company;
use App\Models\Scopes\CompanyScope; // <-- ใช้ Scope ที่คุณสร้างไว้
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\Maintenance\Domain\Models\MaintenanceAssignment;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'job_title',
        'user_id',
        'company_id',
        'position_id',
        'department_id',
        'reports_to_user_id',
        'employee_id_no',
        'join_date',
        'probation_end_date',
        'employment_type',
        'employment_status',
        'hourly_rate',
        'resigned_date',
        'personal_email',
        'phone_number',
        'date_of_birth',
        'gender',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'country',
        'signature_path',
    ];

    protected $casts = [
        'join_date' => 'date',
        'probation_end_date' => 'date',
        'resigned_date' => 'date',
        'date_of_birth' => 'date',
        'hourly_rate' => 'decimal:2',
    ];

    // (สำคัญ) ใช้ Global Scope ของคุณ
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    // --- (Relations ภายใน HRM และ IAM) ---
    public function user(): BelongsTo
    {
        // นี่คือ One-to-One
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function manager()
    {
        // หัวหน้า
        return $this->belongsTo(User::class, 'reports_to_user_id');
    }

    /**
     * กะทำงาน (Shift) ที่พนักงานคนนี้ทำ
     */
    public function workShift()
    {
        return $this->belongsTo(WorkShift::class);
    }

    /**
     * (One-to-Many) ผู้ติดต่อฉุกเฉิน
     */
    public function emergencyContacts()
    {
        return $this->hasMany(EmergencyContact::class);
    }

    /**
     * (One-to-Many) เอกสาร
     */
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    // --- (1. เพิ่ม 3 Relationships ที่ขาดไป) ---

    /**
     * (One-to-Many) ประวัติการลงเวลาทั้งหมด
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * (One-to-Many) ประวัติการลาทั้งหมด
     */
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * (One-to-Many) ประวัติการขอ OT ทั้งหมด
     */
    public function overtimeRequests()
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    // Accessor สำหรับดึง URL รูปภาพ
    public function getSignatureUrlAttribute()
    {
        return $this->signature_path
            ? Storage::url($this->signature_path)
            : null;
    }
}
