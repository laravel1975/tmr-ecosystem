<?php

namespace TmrEcosystem\HRM\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use TmrEcosystem\HRM\Domain\Models\LeaveType; // (Model ที่เราสร้าง)
use TmrEcosystem\HRM\Presentation\Requests\StoreLeaveTypeRequest;
use TmrEcosystem\HRM\Presentation\Requests\UpdateLeaveTypeRequest;
use TmrEcosystem\Shared\Domain\Models\Company;

class LeaveTypeController extends Controller
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
        $leaveTypes = LeaveType::with('company:id,name')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('HRM/LeaveTypes/Index', [
            'leaveTypes' => $leaveTypes,
            'commonData' => $this->getCommonData(),
        ]);
    }

    /**
     * บันทึกประเภทการลาใหม่
     */
    public function store(StoreLeaveTypeRequest $request)
    {
        $data = $request->validated();

        if (!auth()->user()->hasRole('Super Admin')) {
            $data['company_id'] = $request->user()->company_id;
        }

        LeaveType::create($data);

        return redirect()->route('hrm.leave-types.index')->with('success', 'Leave type created.');
    }

    /**
     * อัปเดตประเภทการลา
     */
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType)
    {
        $data = $request->validated();

        if (!auth()->user()->hasRole('Super Admin')) {
            unset($data['company_id']);
        }

        $leaveType->update($data);

        return redirect()->route('hrm.leave-types.index')->with('success', 'Leave type updated.');
    }

    /**
     * ลบประเภทการลา
     */
    public function destroy(LeaveType $leaveType)
    {
        // (ป้องกันการลบ) ตรวจสอบว่ามี "ใบลางาน" (Leave Requests) ใช้งาน Type นี้อยู่หรือไม่
        if ($leaveType->leaveRequests()->exists()) {
            return back()->with('error', 'Cannot delete leave type. It is currently in use by leave requests.');
        }

        $leaveType->delete();
        return redirect()->route('hrm.leave-types.index')->with('success', 'Leave type deleted.');
    }
}
