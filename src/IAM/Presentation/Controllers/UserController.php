<?php

namespace TmrEcosystem\IAM\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use TmrEcosystem\HRM\Domain\Models\EmployeeProfile;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\IAM\Presentation\Requests\StoreUserRequest;
use TmrEcosystem\IAM\Presentation\Requests\UpdateUserRequest;
use TmrEcosystem\Shared\Domain\Models\Company;

class UserController extends Controller
{
    /**
     * แสดงหน้าจอแดชบอร์ด
     */
    public function dashboard()
    {
        return Inertia::render('IAM/Dashboard');
    }

    /**
     * แสดงหน้าจอรายการผู้ใช้
     */
    public function index()
    {
        // (คุณอาจมี Logic การกรองข้อมูล ที่นี่)
        $users = User::with([
            'company:id,name',
            'roles:id,name',

            // --- (สำคัญมาก) ---
            // เพิ่มการโหลดความสัมพันธ์นี้
            // เราต้องการแค่ id ของ profile เพื่อเช็คว่า 'มี' หรือ 'ไม่มี'
            'profile:id,user_id'

        ])
            ->latest()
            // (ถ้าใช้ Paginate ก็ใช้ .paginate() แทน .get())
            ->get();

        return Inertia::render('IAM/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * แสดงหน้าฟอร์มสำหรับสร้างผู้ใช้ใหม่
     */
    public function create(): Response
    {
        // 6. ดึงข้อมูลจาก Shared Kernel และ Spatie
        $companies = Company::where('is_active', true)->select('id', 'name')->get();
        $roles = Role::select('id', 'name')->get();

        return Inertia::render('IAM/Users/Create', [
            'companies' => $companies,
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'company_id' => $validatedData['company_id'],
            'phone' => $validatedData['phone'],
            'avatar_url' => $validatedData['avatar_url'],
            'is_active' => $validatedData['is_active'],
            'password' => Hash::make($validatedData['password']),
        ]);

        $user->syncRoles($validatedData['role_ids']);

        return redirect()->route('iam.index')->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    // --- 2. เพิ่มเมธอด edit ---
    public function edit(User $user): Response
    {
        $companies = Company::where('is_active', true)->select('id', 'name')->get();
        $roles = Role::select('id', 'name')->get();

        return Inertia::render('IAM/Users/Edit', [
            'user' => $user->load('roles:id,name'), // (load roles ของ user)
            'companies' => $companies,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // (ป้องกันการแก้ไขตัวเอง)
        if ($user->id === Auth::user()->id) {
            return redirect()->route('iam.index')->with('error', 'You cannot edit yourself.');
        }

        $validatedData = $request->validated();

        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            unset($validatedData['password']);
        }

        $user->update($validatedData);

        // --- 3. นี่คือส่วนที่แก้ไข ---
        // $user->syncRoles([(int) $validatedData['role_id']]); // (ของเดิม)
        $user->syncRoles($validatedData['role_ids']); // (ของใหม่)

        return redirect()->route('iam.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        // --- 2. Logic ป้องกัน ---
        // กฎที่สำคัญที่สุด: "ห้ามลบตัวเอง"
        if ($user->id === Auth::user()->id) {
            return redirect()->route('iam.index')
                ->with('error', 'You cannot delete yourself.');
        }

        // (เราจะไม่ลบ Super Admin หรือ ตัวเอง)
        if ($user->hasRole('Super Admin')) {
            return redirect()->route('iam.index')->with('error', 'Cannot delete Super Admin.');
        }

        $user->delete(); // User model ไม่มี SoftDeletes (ตามค่าเริ่มต้น)

        return redirect()->route('iam.index')->with('success', 'User deleted successfully.');
    }

    /**
     * (Wizard - ข้อ 1)
     * ค้นหา Employee profile ของ User หรือส่งไปสร้างใหม่
     */
    public function linkToEmployee(Request $request, User $user): RedirectResponse
    {
        // 1. ตรวจสอบว่า User นี้มี Employee profile หรือยัง
        $employee = EmployeeProfile::where('user_id', $user->id)->first();

        if ($employee) {
            // 2. ถ้ามี -> ส่งไปหน้า "Edit"
            return redirect()->route('hrm.employees.edit', $employee->id);
        }

        // 3. ถ้าไม่มี -> ส่งไปหน้า "Create" (ที่เรายังไม่ได้สร้าง)
        // (เราจะสร้าง Route 'hrm.employees.create' ในขั้นตอนถัดไป)
        return redirect()->route('hrm.employees.create', [
            'user_id' => $user->id,
            'name' => $user->name, // (เราจะใช้ 'name' ไปกรอก 'first_name')
            'company_id' => $user->company_id,
        ]);
    }
}
