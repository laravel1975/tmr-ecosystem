<?php

namespace TmrEcosystem\Purchase\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use TmrEcosystem\Purchase\Application\DTOs\PurchaseOrderData;
use TmrEcosystem\Purchase\Domain\Models\PurchaseOrder;
use TmrEcosystem\Purchase\Domain\Models\PurchaseOrderItem;
use TmrEcosystem\Purchase\Domain\Enums\PurchaseOrderStatus;

class CreatePurchaseOrderUseCase
{
    public function execute(PurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            // 1. Calculate Totals
            $subtotal = 0;
            foreach ($data->items as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }
            $taxRate = 0.07; // 7% VAT Example
            $taxAmount = $subtotal * $taxRate;
            $grandTotal = $subtotal + $taxAmount;

            // 2. Generate Document Number
            $latestOrder = PurchaseOrder::latest('id')->first();
            $sequence = $latestOrder ? $latestOrder->id + 1 : 1;
            $docNumber = 'PO-' . date('Ym') . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            // 3. Create PO Header
            $po = PurchaseOrder::create([
                'document_number' => $docNumber,
                'vendor_id' => $data->vendor_id,
                'created_by' => Auth::id(),
                'order_date' => $data->order_date,
                'expected_delivery_date' => $data->expected_delivery_date,
                'status' => PurchaseOrderStatus::DRAFT,
                'notes' => $data->notes,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
            ]);

            // 4. Create PO Items
            foreach ($data->items as $itemData) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                ]);
            }

            return $po;
        });
    }
}
