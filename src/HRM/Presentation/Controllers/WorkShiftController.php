<?php

namespace TmrEcosystem\HRM\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use TmrEcosystem\HRM\Domain\Models\WorkShift; // (Model ที่เราสร้าง)
use TmrEcosystem\HRM\Presentation\Requests\StoreWorkShiftRequest;
use TmrEcosystem\HRM\Presentation\Requests\UpdateWorkShiftRequest;
use TmrEcosystem\Shared\Domain\Models\Company;

class WorkShiftController extends Controller
{
    /**
     * โหลดข้อมูลที่ใช้ร่วมกันสำหรับฟอร์ม (Dropdowns)
     */
    private function getCommonData(): array
    {
        $companies = [];
        // (โหลดรายชื่อบริษัทให้ Super Admin เลือก)
        if (auth()->user()->hasRole('Super Admin')) {
            $companies = Company::select('id', 'name')->get();
        }
        return compact('companies');
    }

    /**
     * แสดงหน้า Index
     */
    public function index(Request $request): Response
    {
        // (CompanyScope จะกรองข้อมูลให้ Admin บริษัทอัตโนมัติ)
        $workShifts = WorkShift::with('company:id,name')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('HRM/WorkShifts/Index', [
            'workShifts' => $workShifts,
            'commonData' => $this->getCommonData(),
        ]);
    }

    /**
     * บันทึกกะทำงานใหม่
     */
    public function store(StoreWorkShiftRequest $request)
    {
        $data = $request->validated();

        // (ถ้าไม่ใช่ Super Admin, บังคับใช้ company_id ของตัวเอง)
        if (!auth()->user()->hasRole('Super Admin')) {
            $data['company_id'] = $request->user()->company_id;
        }

        WorkShift::create($data);

        return redirect()->route('hrm.work-shifts.index')->with('success', 'Work shift created.');
    }

    /**
     * อัปเดตกะทำงาน
     */
    public function update(UpdateWorkShiftRequest $request, WorkShift $workShift)
    {
        $data = $request->validated();

        // (ป้องกัน Admin ธรรมดาแก้ไข company_id)
        if (!auth()->user()->hasRole('Super Admin')) {
            unset($data['company_id']);
        }

        $workShift->update($data);

        return redirect()->route('hrm.work-shifts.index')->with('success', 'Work shift updated.');
    }

    /**
     * ลบกะทำงาน
     */
    public function destroy(WorkShift $workShift)
    {
        // (ควรเช็คก่อนว่ามีพนักงานใช้กะนี้อยู่หรือไม่)
        if ($workShift->employeeProfiles()->exists()) {
            return back()->with('error', 'Cannot delete shift. It is currently assigned to employees.');
        }

        $workShift->delete();
        return redirect()->route('hrm.work-shifts.index')->with('success', 'Work shift deleted.');
    }
}
