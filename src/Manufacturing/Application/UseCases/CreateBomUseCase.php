<?php

namespace TmrEcosystem\Manufacturing\Application\UseCases;

use Illuminate\Support\Facades\DB;
use TmrEcosystem\Manufacturing\Domain\Models\BillOfMaterial;
use TmrEcosystem\Manufacturing\Application\DTOs\CreateBomDto; // (Optional: ถ้าจะทำ DTO แบบเคร่งครัด)

class CreateBomUseCase
{
    public function execute(array $data, string $companyId, string $userId): BillOfMaterial
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {
            // 1. Create Header
            $bom = BillOfMaterial::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'company_id' => $companyId,
                'code' => $data['code'],
                'name' => $data['name'],
                'item_uuid' => $data['item_uuid'],
                'output_quantity' => $data['output_quantity'],
                'version' => '1.0', // Default Version
                'is_active' => true,
                'is_default' => true, // สูตรแรกให้เป็น Default เสมอ
                // 'created_by' => $userId // ถ้ามี field นี้
            ]);

            // 2. Create Components
            foreach ($data['components'] as $component) {
                $bom->components()->create([
                    'component_item_uuid' => $component['item_uuid'],
                    'quantity' => $component['quantity'],
                    'waste_percent' => $component['waste_percent'] ?? 0,
                ]);
            }

            return $bom;
        });
    }
}
