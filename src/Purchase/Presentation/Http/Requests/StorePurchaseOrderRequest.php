<?php

namespace TmrEcosystem\Purchase\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Assuming policy middleware handles generic access
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            // แก้ไข: ตรวจสอบที่ตาราง inventory_items คอลัมน์ uuid
            'items.*.item_id' => ['required', 'exists:inventory_items,uuid'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
