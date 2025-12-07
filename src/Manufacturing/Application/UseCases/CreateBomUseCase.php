<?php

namespace TmrEcosystem\Manufacturing\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use TmrEcosystem\Manufacturing\Domain\Models\BillOfMaterial;

class CreateBomUseCase
{
    public function execute(array $data, string $companyId, string $userId): BillOfMaterial
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {

            // 1. 🔍 ตรวจสอบเวอร์ชันล่าสุด (Auto-Versioning Logic) แก้บั๊ก Duplicate Entry
            $existingBoms = BillOfMaterial::where('company_id', $companyId)
                ->where('item_uuid', $data['item_uuid'])
                ->get();

            // หา Version สูงสุดแล้วบวกเพิ่ม (เช่น 1.0 -> 2.0)
            $maxVersion = $existingBoms->max(fn($b) => (float)$b->version);
            $nextVersion = $maxVersion ? number_format($maxVersion + 1.0, 1) : '1.0';

            // ถ้าเป็น BOM แรกของสินค้านี้ ให้เป็น Default อัตโนมัติ
            $isDefault = $existingBoms->where('is_default', true)->isEmpty();

            // 2. 📝 Create Header (บันทึกข้อมูลหลัก)
            $bom = BillOfMaterial::create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $companyId,
                'code' => $data['code'],
                'name' => $data['name'],
                'item_uuid' => $data['item_uuid'],
                'type' => $data['type'] ?? 'manufacture', // ✅ รองรับ Req 2: Type
                'output_quantity' => $data['output_quantity'],
                'version' => $nextVersion, // ✅ Fix: ใช้เวอร์ชันคำนวณใหม่
                'is_active' => true,
                'is_default' => $isDefault,
            ]);

            // 3. 🔩 Create Components (วัตถุดิบ)
            if (!empty($data['components'])) {
                foreach ($data['components'] as $component) {
                    $bom->components()->create([
                        'component_item_uuid' => $component['item_uuid'],
                        'quantity' => $component['quantity'],
                        'waste_percent' => $component['waste_percent'] ?? 0,
                    ]);
                }
            }

            // 4. ✨ Create By-products (✅ รองรับ Req 3: ผลพลอยได้)
            if (!empty($data['byproducts'])) {
                foreach ($data['byproducts'] as $byproduct) {
                    if (!empty($byproduct['item_uuid']) && !empty($byproduct['quantity'])) {
                        $bom->byproducts()->create([
                            'item_uuid' => $byproduct['item_uuid'],
                            'quantity' => $byproduct['quantity'],
                        ]);
                    }
                }
            }

            return $bom;
        });
    }
}
