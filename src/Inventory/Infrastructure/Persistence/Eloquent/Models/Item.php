<?php

namespace TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models;

// --- 1. Imports ---
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Database\Factories\ItemFactory;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class Item extends Model
{
    // --- 2. Traits ---
    // (เพิ่ม HasFactory เหมือนใน Asset.php และ User.php)
    use HasFactory, SoftDeletes;

    // (ถ้าต้องการ Log การเปลี่ยนแปลง Item ให้เพิ่ม 2 บรรทัดนี้)
    // use Spatie\Activitylog\Traits\LogsActivity;
    // use Spatie\Activitylog\LogOptions;

    /**
     * ชื่อตารางในฐานข้อมูล
     */
    protected $table = 'inventory_items';

    /**
     * บอก Eloquent ว่า UUID คือ Primary Key และไม่ใช่ Auto-increment
     */
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     * (เหมือนใน Asset.php, User.php)
     */
    protected $fillable = [
        'uuid',
        'company_id', // <-- (สำคัญ) เพิ่ม company_id สำหรับ Scope
        'part_number',
        'name',
        'description',
        'category',
        'average_cost',
        'uom',
    ];

    /**
     * --- 3. (สำคัญ) ใช้ Global Scope ของคุณ ---
     * (ใช้รูปแบบเดียวกับ Asset.php และ EmployeeProfile.php)
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * --- 4. (สำคัญ) ใช้เมธอด casts() ---
     * (ใช้รูปแบบเดียวกับ User.php และมาตรฐาน L12)
     */
    protected function casts(): array
    {
        return [
            'average_cost' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * --- 5. (สำคัญ) เชื่อมโยง Factory ---
     * (ใช้รูปแบบเดียวกับ Asset.php และ User.php)
     */
    protected static function newFactory(): ItemFactory
    {
        // (คุณต้องสร้าง ItemFactory ที่ Database\Factories\Inventory\ItemFactory.php)
        return ItemFactory::new();
    }

    // --- 6. Relationships ---

    /**
     * (Relationship ไปยัง Company)
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // (ถ้าต้องการ Log ให้เพิ่มฟังก์ชันนี้ - เหมือนใน User.php)
    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()
    //         ->logOnlyDirty()
    //         ->dontLogIfAttributesChangedOnly(['updated_at'])
    //         ->logFillable()
    //         ->setDescriptionForEvent(fn(string $eventName) => "Inventory Item [{$this->part_number}] has been {$eventName}");
    // }
}
