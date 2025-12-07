<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TmrEcosystem\HRM\Domain\Models\Department; // (HRM Model)
use TmrEcosystem\Shared\Domain\Models\Company;

class DepartmentSeeder extends Seeder
{
    /**
     * (1. [ใหม่] สร้างตัวแปรเก็บโครงสร้างทั้งหมด)
     * เราจะกำหนดโครงสร้างองค์กร (Hierarchy) ของแต่ละบริษัทไว้ที่นี่
     */
    private array $companyHierarchies;

    public function __construct()
    {
        // (โครงสร้าง Back Office ที่ใช้ร่วมกัน)
        $backOffice = [
            ['name' => 'Human Resources', 'description' => 'ฝ่ายทรัพยากรบุคคล'],
            ['name' => 'Accounting & Finance', 'description' => 'ฝ่ายบัญชีและการเงิน'],
            ['name' => 'Sales', 'description' => 'ฝ่ายขาย'],
            ['name' => 'Purchasing', 'description' => 'ฝ่ายจัดซื้อ'],
            ['name' => 'Warehouse & Logistics', 'description' => 'คลังสินค้าและโลจิสติกส์'],
            ['name' => 'IT Support', 'description' => 'ฝ่ายสนับสนุนเทคโนโลยีสารสนเทศ'],
        ];

        // (โครงสร้างซ่อมบำรุง)
        $maintenance = [
            'name' => 'Maintenance',
            'description' => 'ฝ่ายซ่อมบำรุง (วิศวกรรม)',
            'children' => [
                ['name' => 'ME (Mechanical)', 'description' => 'ซ่อมบำรุงเครื่องกล'],
                ['name' => 'FE (Facility)', 'description' => 'ซ่อมบำรุงอาคารและระบบ'],
            ]
        ];

        // (โครงสร้างแผนกทั้งหมด แยกตามบริษัท)
        $this->companyHierarchies = [

            // --- 1. โรงงานเฟอร์นิเจอร์ (ฉีดพลาสติก) ---
            'Modern Furniture Part., Ltd.' => [
                ...$backOffice,
                $maintenance,
                [
                    'name' => 'Production',
                    'description' => 'ฝ่ายผลิต',
                    'children' => [
                        ['name' => 'Production (Injection)', 'description' => 'ฝ่ายผลิต (ฉีดพลาสติก)'],
                        ['name' => 'Production (Assembly)', 'description' => 'ฝ่ายประกอบเก้าอี้/ตู้'],
                    ]
                ],
                [
                    'name' => 'Quality Control (QC)',
                    'description' => 'ฝ่ายควบคุมคุณภาพ',
                    'children' => [
                        ['name' => 'QC (Incoming)', 'description' => 'ตรวจสอบวัตถุดิบ'],
                        ['name' => 'QC (In-Process)', 'description' => 'ตรวจสอบระหว่างผลิต'],
                    ]
                ],
                ['name' => 'Mold', 'description' => 'แผนกแม่พิมพ์'],
            ],

            // --- 2. โรงงาน Royce (รีดพลาสติก) ---
            'Royce Universal Co., Ltd.' => [
                ...$backOffice,
                $maintenance,
                [
                    'name' => 'Production',
                    'description' => 'ฝ่ายผลิต',
                    'children' => [
                        ['name' => 'Production (Extrusion)', 'description' => 'ฝ่ายผลิต (รีดแผ่น PET/PP)'],
                        ['name' => 'Production (Thermoforming)', 'description' => 'ฝ่ายผลิต (ขึ้นรูปบรรจุภัณฑ์)'],
                    ]
                ],
                ['name' => 'Quality Assurance (QA)', 'description' => 'ฝ่ายประกันคุณภาพ (Food Grade)'],
            ],

            // --- 3. โรงงานโคมไฟ (ประกอบ) ---
            'Thai Creative Lighting Co., Ltd.' => [
                ...$backOffice,
                $maintenance,
                ['name' => 'Design & R&D', 'description' => 'ฝ่ายออกแบบและพัฒนาผลิตภัณฑ์ (โคมไฟ)'],
                ['name' => 'Production (Assembly)', 'description' => 'ฝ่ายผลิต (ประกอบโคมไฟ)'],
                ['name' => 'Electrical Engineering', 'description' => 'ฝ่ายวิศวกรรมไฟฟ้าและทดสอบ'],
                ['name' => 'Marketing', 'description' => 'ฝ่ายการตลาด (ออกแบบและดีไซน์)'],
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // (ล้างข้อมูลเก่า)
        Department::query()->delete();

        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->command->error('No companies found. Please run CompanySeeder first.');
            return;
        }

        foreach ($companies as $company) {
            // (ค้นหา Hierarchy ของบริษัทนี้)
            if (isset($this->companyHierarchies[$company->name])) {

                $hierarchyData = $this->companyHierarchies[$company->name];

                // (เริ่มสร้างข้อมูลจาก Top-level)
                foreach ($hierarchyData as $deptData) {
                    $this->createDepartment($company, $deptData, null);
                }
            }
        }

        $this->command->info('Hierarchical Departments seeded successfully.');
    }

    /**
     * (2. [ใหม่] ฟังก์ชัน Recursive)
     * ฟังก์ชันนี้จะสร้างแผนกแม่ และเรียกตัวเองซ้ำเพื่อสร้างแผนกลูก
     */
    private function createDepartment(Company $company, array $deptData, ?int $parentId): void
    {
        // 1. สร้างแผนกแม่
        $department = Department::create([
            'company_id' => $company->id,
            'parent_id' => $parentId, // (เชื่อมโยงแม่)
            'name' => $deptData['name'],
            'description' => $deptData['description'] ?? null,
        ]);

        // 2. ตรวจสอบว่ามีลูกหรือไม่
        if (isset($deptData['children']) && is_array($deptData['children'])) {
            // 3. วนลูปและเรียกตัวเองซ้ำ
            foreach ($deptData['children'] as $childData) {
                // (ส่ง ID ของแผนกที่เพิ่งสร้าง ไปเป็น parent_id ให้ลูก)
                $this->createDepartment($company, $childData, $department->id);
            }
        }
    }
}
