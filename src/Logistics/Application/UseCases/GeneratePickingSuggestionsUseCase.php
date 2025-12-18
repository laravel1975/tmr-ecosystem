<?php

namespace TmrEcosystem\Logistics\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Stock\Application\Contracts\StockPickingServiceInterface;

class GeneratePickingSuggestionsUseCase
{
    public function __construct(
        private StockPickingServiceInterface $stockPickingService
    ) {}

    public function handle(string $pickingSlipId): void
    {
        $pickingSlip = PickingSlip::with('items')->find($pickingSlipId);
        if (!$pickingSlip) {
            throw new Exception("Picking Slip ID {$pickingSlipId} not found.");
        }

        if ($pickingSlip->status !== 'draft') {
            throw new Exception("Picking suggestions can only be generated for DRAFT picking slips.");
        }

        // 1. รวบรวมรายการสินค้าที่ต้องการ
        $itemsToPlan = [];
        foreach ($pickingSlip->items as $item) {
            $itemsToPlan[] = [
                'product_id' => $item->item_id,
                'quantity' => $item->quantity_requested
            ];
        }

        // 2. เรียก Stock Service เพื่อขอแผนการหยิบ
        // ✅ ตรวจสอบบรรทัดนี้: ต้องเรียก 'planPicking' ให้ตรงกับ Interface/Service
        $plan = $this->stockPickingService->planPicking($pickingSlip->warehouse_id, $itemsToPlan);

        // 3. ปรับปรุงรายการใน Picking Slip
        DB::transaction(function () use ($pickingSlip, $plan) {
            // ลบรายการเดิม
            $pickingSlip->items()->delete();

            // สร้างรายการใหม่ตามแผน
            foreach ($plan as $suggestion) {
                PickingSlipItem::create([
                    'picking_slip_id' => $pickingSlip->id,
                    'item_id' => $suggestion['product_id'],
                    'item_name' => '', // ควรดึงชื่อสินค้าจริงมาใส่
                    'quantity_requested' => $suggestion['quantity'],
                    'quantity_picked' => 0,
                    'location_code' => $suggestion['location_code'],
                    'location_uuid' => $suggestion['location_uuid'] ?? null,
                    'status' => 'pending'
                ]);
            }

            $pickingSlip->update(['status' => 'open']);

            Log::info("Generated picking plan for Slip {$pickingSlip->order_number}");
        });
    }
}
