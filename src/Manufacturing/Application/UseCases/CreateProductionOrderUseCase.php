<?php

namespace TmrEcosystem\Manufacturing\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use TmrEcosystem\Manufacturing\Domain\Models\ProductionOrder;
use TmrEcosystem\Manufacturing\Domain\Models\BillOfMaterial;
use Exception;

class CreateProductionOrderUseCase
{
    public function execute(array $data, string $companyId, string $userId): ProductionOrder
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {

            // 1. หา BOM ที่เป็น Default ของสินค้านี้
            $bom = BillOfMaterial::where('item_uuid', $data['item_uuid'])
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();

            if (!$bom) {
                throw new Exception("ไม่พบสูตรการผลิต (Default BOM) สำหรับสินค้านี้ กรุณาสร้าง BOM ก่อน");
            }

            // 2. Generate Running Number (Format: MO-YYYYMM-XXXX)
            $prefix = 'MO-' . date('Ym') . '-';
            $lastOrder = ProductionOrder::where('company_id', $companyId)
                ->where('order_number', 'like', $prefix . '%')
                ->orderBy('order_number', 'desc')
                ->first();

            $nextNumber = $lastOrder
                ? intval(substr($lastOrder->order_number, -4)) + 1
                : 1;

            $orderNumber = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // 3. Create Order
            $order = ProductionOrder::create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $companyId,
                'order_number' => $orderNumber,
                'item_uuid' => $data['item_uuid'],
                'bom_uuid' => $bom->uuid, // Link กับ BOM
                'planned_quantity' => $data['planned_quantity'],
                'planned_start_date' => $data['planned_start_date'],
                'planned_end_date' => $data['planned_end_date'] ?? null,
                'status' => 'planned', // สถานะเริ่มต้น
                // 'created_by' => $userId
            ]);

            return $order;
        });
    }
}
