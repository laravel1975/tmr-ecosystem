<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
// --- (Imports ที่เราต้องใช้) ---
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\IAM\Domain\Models\Role;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    /**
     * แสดงหน้า Main Dashboard
     */
    public function index(): Response
    {
        // --- 1. การ์ดสรุป (Stat Cards) ---
        // $userCount = User::count();
        // $companyCount = Company::count();
        // $roleCount = Role::count();
        // // (นับ Log ที่ Login ผิด ใน 24 ชั่วโมงที่ผ่านมา)
        // $failedLogins = Activity::where('description', 'User failed to log in')
        //                         ->where('created_at', '>=', now()->subDay())
        //                         ->count();

        // // --- 2. วิดเจ็ตกิจกรรมล่าสุด (Recent Activity) ---
        // $recentActivity = Activity::with('causer:id,name')
        //                           ->latest()
        //                           ->limit(5) // (ดึงมา 5 รายการล่าสุด)
        //                           ->get();

        // // --- 3. ส่งข้อมูลไปให้ Frontend ---
        // return Inertia::render('Dashboard', [
        //     'stats' => [
        //         'users' => $userCount,
        //         'companies' => $companyCount,
        //         'roles' => $roleCount,
        //         'failedLogins' => $failedLogins,
        //     ],
        //     'recentActivity' => $recentActivity,
        // ]);

        return Inertia::render('ApplicationPanel');
    }
}
