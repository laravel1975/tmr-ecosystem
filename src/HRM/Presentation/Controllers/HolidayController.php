<?php

namespace TmrEcosystem\HRM\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use TmrEcosystem\HRM\Domain\Models\Holiday; // (Model ที่เราสร้าง)
use TmrEcosystem\HRM\Presentation\Requests\StoreHolidayRequest;
use TmrEcosystem\HRM\Presentation\Requests\UpdateHolidayRequest;
use TmrEcosystem\Shared\Domain\Models\Company;

class HolidayController extends Controller
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
        $holidays = Holiday::with('company:id,name')
            ->latest('date') // (เรียงตามวันที่ล่าสุดก่อน)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('HRM/Holidays/Index', [
            'holidays'   => $holidays,
            'commonData' => $this->getCommonData(),
        ]);
    }

    /**
     * บันทึกวันหยุดใหม่
     */
    public function store(StoreHolidayRequest $request)
    {
        $data = $request->validated();

        if (!auth()->user()->hasRole('Super Admin')) {
            $data['company_id'] = $request->user()->company_id;
        }

        Holiday::create($data);

        return redirect()->route('hrm.holidays.index')->with('success', 'Holiday created.');
    }

    /**
     * อัปเดตวันหยุด
     */
    public function update(UpdateHolidayRequest $request, Holiday $holiday)
    {
        $data = $request->validated();

        if (!auth()->user()->hasRole('Super Admin')) {
            unset($data['company_id']);
        }

        $holiday->update($data);

        return redirect()->route('hrm.holidays.index')->with('success', 'Holiday updated.');
    }

    /**
     * ลบวันหยุด
     */
    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return redirect()->route('hrm.holidays.index')->with('success', 'Holiday deleted.');
    }
}
