<?php

namespace TmrEcosystem\Shared\Infrastructure\Persistence\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use TmrEcosystem\IAM\Domain\Models\User; // ✅ Import User Model ที่ถูกต้อง

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // ตรวจสอบว่ามีผู้ใช้ล็อกอินอยู่หรือไม่
        if (Auth::check()) {

            /** @var \TmrEcosystem\IAM\Domain\Models\User $user */
            $user = Auth::user();

            // ✅ 2. ตรวจสอบ Role (Safety Check)
            // เช็คว่า user object มี method hasRole จริงไหม ป้องกัน Crash
            if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
                return; // Super Admin เห็นทุกอย่าง
            }

            // ✅ 3. ดึงชื่อตารางเพื่อระบุ Column ให้ชัดเจน (ป้องกัน Ambiguous column error เมื่อ Join)
            $tableName = $model->getTable();

            // ถ้าผู้ใช้ทั่วไป ให้กรองตาม Company ID
            if ($user->company_id) {
                $builder->where("{$tableName}.company_id", $user->company_id);
            } else {
                // กรณีหลุด: User ไม่มี company_id ให้ไม่เห็นข้อมูลเลย (เพื่อความปลอดภัย)
                $builder->whereRaw('1 = 0');
            }
        }
    }
}
