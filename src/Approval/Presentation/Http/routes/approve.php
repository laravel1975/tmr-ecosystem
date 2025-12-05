<?php

use Illuminate\Support\Facades\Route;
use TmrEcosystem\Approval\Application\UseCases\SubmitRequestUseCase;
use TmrEcosystem\Approval\Presentation\Http\Controllers\ApprovalPdfController;
use TmrEcosystem\Approval\Presentation\Http\Controllers\ApprovalRequestController;

Route::group(['middleware' => ['web', 'auth']], function () {

    // หน้า Dashboard รายการอนุมัติ
    Route::get('/approvals', [ApprovalRequestController::class, 'index'])
        ->name('approval.index');

    // ปุ่มกด Action (Approve/Reject)
    Route::post('/approvals/action', [ApprovalRequestController::class, 'action'])
        ->name('approval.action');

    Route::get('/approval-requests/{id}/print', [ApprovalPdfController::class, 'print'])->name('approval.print');

    Route::get('/test-approval-submit', function (SubmitRequestUseCase $submitService) {

        try {
            // Case A: ราคาแพง (ต้องอนุมัติ 2 คน)
            $req1 = $submitService->handle(
                workflowCode: 'MAINTENANCE_WO_FLOW',
                subjectType: 'WorkOrder',
                subjectId: 'TEST-WO-' . rand(1000, 9999),
                requesterId: 1,
                payload: ['estimated_cost' => 8500]
            );

            // Case B: ราคาถูก (อนุมัติคนเดียวจบ)
            $req2 = $submitService->handle(
                workflowCode: 'MAINTENANCE_WO_FLOW',
                subjectType: 'WorkOrder',
                subjectId: 'TEST-WO-' . rand(1000, 9999),
                requesterId: 1,
                payload: ['estimated_cost' => 2000]
            );

            return response()->json([
                'scenario_1_expensive' => $req1->load('currentStep'),
                'scenario_2_cheap' => $req2->load('currentStep'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
});
