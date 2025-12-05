<?php

namespace TmrEcosystem\Approval\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use TmrEcosystem\Approval\Domain\Models\ApprovalRequest;
use Spatie\Browsershot\Browsershot; // ใช้ Library ยอดนิยม หรือ Snappy ตามที่คุณลงไว้
use TmrEcosystem\Shared\Infrastructure\Services\BrowsershotPdfService;

class ApprovalPdfController extends Controller
{
    public function __construct(
        protected BrowsershotPdfService $pdfService
    ) {}

    public function print(string $id)
    {
        // 1. ดึงข้อมูลให้ครบ (Eager Loading) เพื่อประสิทธิภาพ
        $request = ApprovalRequest::with([
            'workflow.steps',
            'requester',
            'actions.actor.employeeProfile' // ดึง EmployeeProfile เพื่อเอา signature_path
        ])->findOrFail($id);

        // 2. Render Blade เป็น HTML string
        $html = view('pdf.approval.sales_request', ['request' => $request])->render();

        // --- Option B: ถ้าใช้ Browsershot (สวยสุด รองรับ Tailwind) ---
        $pdf = Browsershot::html($html)
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->pdf();

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="approval-' . $request->document_number . '.pdf"');
    }
}
