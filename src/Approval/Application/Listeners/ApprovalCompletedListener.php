<?php

namespace TmrEcosystem\Approval\Application\Listeners;

use TmrEcosystem\Approval\Domain\Events\WorkflowCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

// Models ที่เกี่ยวข้อง
use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;

class ApprovalCompletedListener implements ShouldQueue
{
    public function handle(WorkflowCompleted $event)
    {
        $request = $event->request;
        $workflowCode = $request->workflow->code;

        // เราใช้ subject_id และ subject_type เพื่อดึง Model จริง
        $subjectId = $request->subject_id;
        $payload = $request->payload_snapshot ?? [];

        Log::info("Workflow Completed: {$workflowCode} for ID: {$subjectId}");

        match ($workflowCode) {
            // --- 💰 Price & Sales ---
            'SALES_DISCOUNT_APPROVE' => $this->handleDiscountApproved($subjectId, $payload),
            'SALES_PRICE_APPROVE' => $this->handlePriceApproved($subjectId),
            'SALES_QUOTATION_APPROVE' => $this->handleQuotationApproved($subjectId),

            // --- 👥 Customer & Credit ---
            'CRM_NEW_CUSTOMER' => $this->handleNewCustomerApproved($subjectId),
            'FINANCE_CREDIT_LIMIT' => $this->handleCreditLimitApproved($subjectId, $payload),

            // --- 🏭 Production ---
            'PROD_URGENT_ORDER' => $this->handleUrgentOrderApproved($subjectId),
            'PROD_START_JOB' => $this->handleProductionStart($subjectId),

            // --- 🔄 Others ---
            'LOG_RMA_APPROVE' => $this->handleRmaApproved($subjectId),

            default => Log::warning("No handler found for workflow: {$workflowCode}"),
        };
    }

    // --- Business Logic Handlers ---

    private function handleDiscountApproved($orderId, $payload)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update([
                'status' => 'confirmed', // อนุมัติแล้วเปลี่ยนสถานะ
                'discount_percent' => $payload['discount_percent'] ?? 0,
                'approved_at' => now()
            ]);
            // TODO: ส่ง Email แจ้ง Sales กลับ
        }
    }

    private function handleNewCustomerApproved($customerId)
    {
        $customer = Customer::find($customerId);
        if ($customer) {
            $customer->update([
                'status' => 'active',
                'is_verified' => true
            ]);
        }
    }

    private function handleCreditLimitApproved($customerId, $payload)
    {
        $customer = Customer::find($customerId);
        if ($customer && isset($payload['new_credit_limit'])) {
            $customer->update([
                'credit_limit' => $payload['new_credit_limit']
            ]);
        }
    }

    private function handleUrgentOrderApproved($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['priority' => 'urgent', 'production_status' => 'scheduled']);
        }
    }

    // ... สร้าง Handler สำหรับเคสที่เหลือ ...
    private function handlePriceApproved($id) { /* Logic */ }
    private function handleQuotationApproved($id) { /* Logic */ }
    private function handleProductionStart($id) { /* Logic */ }
    private function handleRmaApproved($id) { /* Logic */ }
}
