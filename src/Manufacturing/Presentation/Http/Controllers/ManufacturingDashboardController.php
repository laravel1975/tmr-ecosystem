<?php

namespace TmrEcosystem\Manufacturing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class ManufacturingDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $request->user()->company_id;

        // 1. ข้อมูล BOM (Bill of Materials)
        $totalBoms = DB::table('manufacturing_boms')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->count();

        // 2. ข้อมูล Production Orders (แยกตามสถานะ)
        $poStats = DB::table('manufacturing_production_orders')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // คำนวณยอดรวมทั้งหมด
        $totalOrders = array_sum($poStats);

        return Inertia::render('Manufacturing/Dashboard', [
            'stats' => [
                'boms' => [
                    'total' => $totalBoms,
                    'active' => DB::table('manufacturing_boms') // นับเฉพาะ Active
                        ->where('company_id', $companyId)
                        ->where('is_active', true)
                        ->count(),
                ],
                'orders' => [
                    'total' => $totalOrders,
                    'draft' => $poStats['draft'] ?? 0,
                    'planned' => $poStats['planned'] ?? 0,
                    'in_progress' => $poStats['in_progress'] ?? 0,
                    'completed' => $poStats['completed'] ?? 0,
                ]
            ]
        ]);
    }
}
