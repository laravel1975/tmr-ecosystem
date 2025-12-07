<?php

namespace TmrEcosystem\HRM\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use TmrEcosystem\HRM\Domain\Models\Department;
use TmrEcosystem\HRM\Presentation\Requests\StoreDepartmentRequest;
use TmrEcosystem\HRM\Presentation\Requests\UpdateDepartmentRequest;
use TmrEcosystem\Shared\Domain\Models\Company;

class DepartmentController extends Controller
{
    /**
     * (2. สร้าง) โหลดข้อมูลที่ใช้ร่วมกันสำหรับฟอร์ม (Dropdowns)
     */
    private function getCommonData(): array
    {
        // (CompanyScope จะกรอง Department และ User ตามสิทธิ์อยู่แล้ว)
        // เราดึง 'company_id' มาเพื่อใช้กรองใน React
        $departments = Department::select('id', 'name', 'company_id')->get();

        $companies = [];
        if (auth()->user()->hasRole('Super Admin')) {
            $companies = Company::select('id', 'name')->get();
        }

        return compact('departments', 'companies');
    }

    /**
     * (3. แก้ไข) แสดงหน้าจอรายการ Departments
     */
    public function index(Request $request): Response
    {
        // (CompanyScope จะกรองให้เราอัตโนมัติ)
        // (เพิ่ม with() เพื่อโหลดความสัมพันธ์มาแสดงในตาราง)
        $departments = Department::with(['company:id,name', 'parent:id,name'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('HRM/Departments/Index', [
            'departments' => $departments,
            'commonData'  => $this->getCommonData(), // (ส่ง commonData ไปให้ Modal)
        ]);
    }

    /**
     * (4. แก้ไข) บันทึก Department ใหม่
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // (Logic ใหม่) ถ้าไม่ใช่ Super Admin, ให้บังคับใช้ company_id ของตัวเอง
        if (!auth()->user()->hasRole('Super Admin')) {
            $data['company_id'] = $request->user()->company_id;
        }

        Department::create($data);

        return redirect()->route('hrm.departments.index')
                         ->with('success', 'Department created successfully.');
    }

    /**
     * (5. แก้ไข) อัปเดต Department
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $data = $request->validated();

        // (Logic ใหม่) ป้องกัน Admin ธรรมดาแก้ไข company_id
        if (!auth()->user()->hasRole('Super Admin')) {
            // (ลบ company_id ออกจาก data ที่จะอัปเดต)
            unset($data['company_id']);
        }

        $department->update($data);

        return redirect()->route('hrm.departments.index')
                         ->with('success', 'Department updated successfully.');
    }

    /**
     * ลบ Department (เหมือนเดิม)
     */
    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()->route('hrm.departments.index')
                         ->with('success', 'Department deleted successfully.');
    }
}
