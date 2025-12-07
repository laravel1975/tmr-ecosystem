<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use TmrEcosystem\Shared\Domain\Models\Company;

class CompanyController extends Controller
{
    /**
     * แสดงหน้าจอรายการบริษัท
     */
    public function index()
    {
        $companies = Company::latest()->get();

        return Inertia::render('Company/Index', [
            'companies' => $companies,
        ]);
    }

    /**
     * แสดงหน้าฟอร์มสำหรับสร้างบริษัท
     */
    public function create()
    {
        return Inertia::render('Company/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    // --- 3. (สำคัญ) เพิ่ม Method นี้ทั้งหมด ---
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        // ข้อมูลที่ผ่านการ Validate จาก StoreCompanyRequest แล้ว
        $validatedData = $request->validated();

        // สร้าง Company
        Company::create($validatedData);

        // ส่งกลับไปหน้า Index พร้อมข้อความ success
        return redirect()->route('companies.index')->with('success', 'Company created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company): Response
    {
        // $company มาจาก Route-Model Binding (จาก {company} ใน route)
        return Inertia::render('Company/Edit', [
            'company' => $company
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        // $request ผ่านการ validate แล้ว
        $company->update($request->validated());

        return redirect()->route('companies.index')->with('success', 'Company updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company): RedirectResponse
    {
        // (ไฟล์ Company.php ของคุณมี SoftDeletes อยู่แล้ว)
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Company deleted successfully.');
    }
}
