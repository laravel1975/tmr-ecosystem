<?php

namespace TmrEcosystem\HRM\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use TmrEcosystem\HRM\Domain\Models\Department;
use TmrEcosystem\HRM\Domain\Models\Position; // (Model ใหม่)
use TmrEcosystem\HRM\Presentation\Requests\StorePositionRequest; // (Request ใหม่)
use TmrEcosystem\HRM\Presentation\Requests\UpdatePositionRequest; // (Request ใหม่)
use TmrEcosystem\Shared\Domain\Models\Company;

class PositionController extends Controller
{
    /**
     * โหลดข้อมูลที่ใช้ร่วมกันสำหรับฟอร์ม (Dropdowns)
     */
    private function getCommonData(): array
    {
        // (เราต้องใช้ Department และ Company สำหรับ Dropdown)
        $departments = Department::select('id', 'name', 'company_id')->get();

        $companies = [];
        if (auth()->user()->hasRole('Super Admin')) {
            $companies = Company::select('id', 'name')->get();
        }

        return compact('departments', 'companies');
    }

    /**
     * แสดงหน้า Index
     */
    public function index(Request $request): Response
    {
        // 1. ดึงข้อมูลตำแหน่งงาน (แบบ Paginate)
        $positions = Position::with([
            'department:id,name',
            'company:id,name'
        ])
        ->latest()
        ->paginate(15)
        ->withQueryString();

        // 2. ดึงข้อมูลสำหรับ Modal
        $commonData = $this->getCommonData();

        // 3. (Wizard Logic) ตรวจสอบว่าต้องเปิด Modal Edit หรือไม่
        $positionToEdit = null;
        if ($request->action === 'edit' && $request->id) {
            $positionToEdit = Position::find($request->id);
        }

        // 4. ส่ง Props ทั้งหมดไปให้ React
        return Inertia::render('HRM/Positions/Index', [
            'positions'      => $positions,
            'commonData'     => $commonData,
            'query'          => $request->only(['action', 'id']),
            'positionToEdit' => $positionToEdit,
        ]);
    }

    /**
     * บันทึกตำแหน่งงานใหม่
     */
    public function store(StorePositionRequest $request)
    {
        $validated = $request->validated();

        if (!auth()->user()->hasRole('Super Admin')) {
            $validated['company_id'] = auth()->user()->company_id;
        }

        Position::create($validated);

        return redirect()->route('hrm.positions.index')->with('success', 'Position created.');
    }

    /**
     * อัปเดตตำแหน่งงาน
     */
    public function update(UpdatePositionRequest $request, Position $position)
    {
        $validated = $request->validated();

        if (!auth()->user()->hasRole('Super Admin')) {
            $validated['company_id'] = $position->company_id;
        }

        $position->update($validated);

        return redirect()->route('hrm.positions.index')->with('success', 'Position updated.');
    }

    /**
     * ลบตำแหน่งงาน
     */
    public function destroy(Position $position)
    {
        $position->delete();
        return redirect()->route('hrm.positions.index')->with('success', 'Position deleted.');
    }
}
