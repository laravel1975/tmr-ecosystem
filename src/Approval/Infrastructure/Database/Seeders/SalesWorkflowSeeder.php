<?php

namespace TmrEcosystem\Approval\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\Approval\Domain\Models\ApprovalWorkflow;
use TmrEcosystem\Approval\Domain\Models\ApprovalWorkflowStep;

class SalesWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // รายการ Workflow ทั้ง 15 ข้อ
        $workflows = [
            // --- 💰 Price & Sales ---
            [
                'name' => 'Special Price Approval',
                'code' => 'SALES_PRICE_APPROVE',
                'desc' => 'ขออนุมัติราคาพิเศษต่ำกว่าเกณฑ์',
                'steps' => [
                    ['order' => 1, 'role' => 'SalesManager'],
                    ['order' => 2, 'role' => 'CommercialDirector', 'condition' => ['margin' => ['operator' => '<', 'value' => 10]]]
                ]
            ],
            [
                'name' => 'Discount Approval',
                'code' => 'SALES_DISCOUNT_APPROVE',
                'desc' => 'ขออนุมัติส่วนลดเพิ่มเติม',
                'steps' => [
                    ['order' => 1, 'role' => 'SalesManager'],
                    ['order' => 2, 'role' => 'GM', 'condition' => ['discount_percent' => ['operator' => '>', 'value' => 15]]]
                ]
            ],
            [
                'name' => 'Quotation Approval',
                'code' => 'SALES_QUOTATION_APPROVE',
                'desc' => 'อนุมัติใบเสนอราคาก่อนส่งลูกค้า',
                'steps' => [
                    ['order' => 1, 'role' => 'SalesSupervisor']
                ]
            ],

            // --- 👥 Customer & Credit ---
            [
                'name' => 'New Customer Opening',
                'code' => 'CRM_NEW_CUSTOMER',
                'desc' => 'อนุมัติเปิดลูกค้าใหม่ (ตรวจสอบเอกสาร)',
                'steps' => [
                    ['order' => 1, 'role' => 'SalesAdmin'],
                    ['order' => 2, 'role' => 'FinanceManager']
                ]
            ],
            [
                'name' => 'Credit Limit Approval',
                'code' => 'FINANCE_CREDIT_LIMIT',
                'desc' => 'อนุมัติวงเงินเครดิตลูกค้า',
                'steps' => [
                    ['order' => 1, 'role' => 'FinanceManager'],
                    ['order' => 2, 'role' => 'Director', 'condition' => ['credit_amount' => ['operator' => '>', 'value' => 1000000]]]
                ]
            ],

            // --- 🏭 Production & Operations ---
            [
                'name' => 'Urgent Order Request',
                'code' => 'PROD_URGENT_ORDER',
                'desc' => 'ขอแทรกคิวผลิต/งานด่วน',
                'steps' => [
                    ['order' => 1, 'role' => 'ProductionManager'],
                    ['order' => 2, 'role' => 'PlantManager']
                ]
            ],
            [
                'name' => 'Product Spec Change',
                'code' => 'QC_SPEC_CHANGE',
                'desc' => 'อนุมัติแก้ไขสเปคสินค้า',
                'steps' => [
                    ['order' => 1, 'role' => 'QCManager'],
                    ['order' => 2, 'role' => 'RDManager']
                ]
            ],
            [
                'name' => 'Artwork / Packaging Approval',
                'code' => 'MKT_ARTWORK_APPROVE',
                'desc' => 'อนุมัติแบบ Artwork หรือ Packaging',
                'steps' => [
                    ['order' => 1, 'role' => 'MarketingManager'],
                    ['order' => 2, 'role' => 'CustomerRep'] // อาจจะเป็น Sales เซ็นแทนลูกค้า
                ]
            ],
            [
                'name' => 'New Mold Opening',
                'code' => 'ENG_NEW_MOLD',
                'desc' => 'อนุมัติเปิดแม่พิมพ์ใหม่',
                'steps' => [
                    ['order' => 1, 'role' => 'EngineeringManager'],
                    ['order' => 2, 'role' => 'Director']
                ]
            ],
            [
                'name' => 'Production Start (Job Order)',
                'code' => 'PROD_START_JOB',
                'desc' => 'อนุมัติเปิดใบสั่งผลิต (Job Order)',
                'steps' => [
                    ['order' => 1, 'role' => 'ProductionPlanner']
                ]
            ],

            // --- 🔄 After Sales & General ---
            [
                'name' => 'RMA / Return Approval',
                'code' => 'LOG_RMA_APPROVE',
                'desc' => 'อนุมัติรับคืนสินค้า',
                'steps' => [
                    ['order' => 1, 'role' => 'QCManager'], // ตรวจสภาพของ
                    ['order' => 2, 'role' => 'SalesManager'] // อนุมัติรับคืน
                ]
            ],
            [
                'name' => 'New Project Kickoff',
                'code' => 'GEN_NEW_PROJECT',
                'desc' => 'อนุมัติเริ่มโปรเจคใหม่',
                'steps' => [
                    ['order' => 1, 'role' => 'ProjectManager'],
                    ['order' => 2, 'role' => 'Director']
                ]
            ],
            [
                'name' => 'Claim / Warranty Request',
                'code' => 'QC_CLAIM_REQUEST',
                'desc' => 'อนุมัติเคลมสินค้าเสียหาย',
                'steps' => [
                    ['order' => 1, 'role' => 'QCManager'],
                    ['order' => 2, 'role' => 'FactoryManager']
                ]
            ],
            [
                'name' => 'Replacement Delivery',
                'code' => 'SALES_REPLACEMENT',
                'desc' => 'อนุมัติส่งของทดแทน (ไม่มีค่าใช้จ่าย)',
                'steps' => [
                    ['order' => 1, 'role' => 'SalesDirector'],
                    ['order' => 2, 'role' => 'FinanceManager'] // รับทราบเรื่องตัดสต็อก
                ]
            ],
            [
                'name' => 'Additional Expense',
                'code' => 'ACC_EXTRA_EXPENSE',
                'desc' => 'อนุมัติค่าใช้จ่ายเพิ่มเติม',
                'steps' => [
                    ['order' => 1, 'role' => 'DepartmentHead'],
                    ['order' => 2, 'role' => 'CFO', 'condition' => ['amount' => ['operator' => '>', 'value' => 50000]]]
                ]
            ],
        ];

        foreach ($workflows as $wfData) {
            $workflow = ApprovalWorkflow::firstOrCreate(
                ['code' => $wfData['code']],
                [
                    'name' => $wfData['name'],
                    'description' => $wfData['desc'],
                    'is_active' => true
                ]
            );

            // Clear old steps if re-seeding
            $workflow->steps()->delete();

            foreach ($wfData['steps'] as $stepData) {
                ApprovalWorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'order' => $stepData['order'],
                    'approver_role' => $stepData['role'],
                    'conditions' => $stepData['condition'] ?? null
                ]);
            }
        }
    }
}
