<?php

namespace TmrEcosystem\Sales\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use TmrEcosystem\Approval\Application\UseCases\SubmitRequestUseCase;
use TmrEcosystem\Approval\Domain\Models\ApprovalRequest;
// Models
use TmrEcosystem\Sales\Domain\Aggregates\Order; // ตัวอย่าง Model
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;

class SalesApprovalController extends Controller
{
    public function __construct(
        protected SubmitRequestUseCase $submitService
    ) {}

    // รายชื่อ Workflow Code ทั้ง 15 ข้อของฝ่ายขาย
    const SALES_WORKFLOW_CODES = [
        'SALES_PRICE_APPROVE',
        'SALES_DISCOUNT_APPROVE',
        'SALES_QUOTATION_APPROVE',
        'CRM_NEW_CUSTOMER',
        'FINANCE_CREDIT_LIMIT',
        'PROD_URGENT_ORDER',
        'QC_SPEC_CHANGE',
        'MKT_ARTWORK_APPROVE',
        'ENG_NEW_MOLD',
        'PROD_START_JOB',
        'LOG_RMA_APPROVE',
        'GEN_NEW_PROJECT',
        'QC_CLAIM_REQUEST',
        'SALES_REPLACEMENT',
        'ACC_EXTRA_EXPENSE'
    ];

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'pending');

        // 1. Query หลัก: ดึงเฉพาะ Workflow ของ Sales
        $query = ApprovalRequest::query()
            ->with(['workflow', 'requester', 'currentStep'])
            ->whereHas('workflow', function ($q) {
                $q->whereIn('code', self::SALES_WORKFLOW_CODES);
            });

        // 2. Filter ตาม User (ดูเฉพาะงานที่ฉันต้องอนุมัติ)
        // หมายเหตุ: ถ้าเป็น Admin ให้ดูได้หมด, ถ้าเป็น User ธรรมดา ให้ดูเฉพาะที่ตัวเองมีสิทธิ์ (ตาม Role ใน Step)
        // $userRoles = Auth::user()->getRoleNames(); // ต้องใช้ Spatie/Permission
        // $query->whereHas('currentStep', fn($q) => $q->whereIn('approver_role', $userRoles));

        // 3. Search & Filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                    ->orWhere('subject_id', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // 4. Get Data
        $approvals = $query->orderBy('created_at', 'desc')->paginate(10);

        // 5. Calculate Stats (KPIs)
        $statsQuery = ApprovalRequest::whereHas('workflow', fn($q) => $q->whereIn('code', self::SALES_WORKFLOW_CODES));

        $stats = [
            'total_pending'   => (clone $statsQuery)->where('status', 'pending')->count(),
            // งานด่วน: ดูจาก Code หรือ Payload
            'urgent_tasks'    => (clone $statsQuery)->where('status', 'pending')
                ->where(function ($q) {
                    $q->whereHas('workflow', fn($w) => $w->where('code', 'PROD_URGENT_ORDER')) // กรณี Workflow งานด่วนโดยเฉพาะ
                        ->orWhereRaw("JSON_EXTRACT(payload_snapshot, '$.is_urgent') = true"); // หรือมี Flag urgent ใน payload
                })->count(),
            'completed_today' => (clone $statsQuery)->whereIn('status', ['approved', 'rejected'])
                ->whereDate('updated_at', today())->count(),
        ];

        return Inertia::render('Sales/Approvals/Index', [
            'approvals' => $approvals,
            'filters'   => ['search' => $search, 'status' => $status],
            'stats'     => $stats
        ]);
    }

    /**
     * ข้อ 2: ขออนุมัติส่วนลด
     */
    public function requestDiscount(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        $discountPercent = $request->input('discount_percent');

        // Update Order รอไว้ก่อน
        $order->update(['status' => 'waiting_approval', 'requested_discount' => $discountPercent]);

        $this->submitService->handle(
            workflowCode: 'SALES_DISCOUNT_APPROVE',
            subjectType: Order::class,
            subjectId: $order->id,
            requesterId: Auth::id(),
            payload: [
                'discount_percent' => $discountPercent,
                'total_amount' => $order->total_amount
            ]
        );

        return back()->with('success', 'ส่งคำขออนุมัติส่วนลดเรียบร้อยแล้ว');
    }

    /**
     * ข้อ 4: ขออนุมัติลูกค้าใหม่
     */
    public function requestNewCustomer($customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $customer->update(['status' => 'pending_verification']);

        $this->submitService->handle(
            workflowCode: 'CRM_NEW_CUSTOMER',
            subjectType: Customer::class,
            subjectId: $customer->id,
            requesterId: Auth::id(),
            payload: ['customer_type' => $customer->type]
        );

        return back()->with('success', 'ส่งเอกสารลูกค้าตรวจสอบเรียบร้อย');
    }

    /**
     * ข้อ 6: ขออนุมัติงานด่วน (Urgent Order)
     */
    public function requestUrgentOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        $this->submitService->handle(
            workflowCode: 'PROD_URGENT_ORDER',
            subjectType: Order::class,
            subjectId: $order->id,
            requesterId: Auth::id(),
            payload: [
                'is_urgent' => true,
                'reason' => 'Customer production line down'
            ]
        );

        return back()->with('success', 'ส่งคำขอแทรกคิวผลิตด่วนแล้ว');
    }

    // ... สามารถเพิ่ม Method อื่นๆ สำหรับข้อ 1-15 ตาม Pattern นี้ได้เลย ...
}
