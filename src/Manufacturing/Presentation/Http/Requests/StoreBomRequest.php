<?php

namespace TmrEcosystem\Manufacturing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StoreBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = Auth::user()->company_id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                // Check Unique เฉพาะในบริษัทตัวเอง
                Rule::unique('manufacturing_boms')->where('company_id', $companyId)
            ],
            'name' => ['required', 'string', 'max:255'],
            'item_uuid' => [
                'required',
                'uuid',
                // สินค้าที่จะผลิต ต้องมีอยู่จริงใน Inventory ของบริษัทเรา
                Rule::exists('inventory_items', 'uuid')->where('company_id', $companyId)
            ],
            'output_quantity' => ['required', 'numeric', 'min:0.0001'],

            // Validate Array ของวัตถุดิบ
            'components' => ['required', 'array', 'min:1'],
            'components.*.item_uuid' => [
                'required',
                'uuid',
                'distinct', // ห้ามใส่วัตถุดิบซ้ำกันในสูตรเดียว
                Rule::exists('inventory_items', 'uuid')->where('company_id', $companyId)
            ],
            'components.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'components.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages()
    {
        return [
            'item_uuid.required' => 'Please select a finished good.',
            'components.required' => 'At least one raw material is required.',
            'components.*.item_uuid.distinct' => 'Duplicate raw material selected.',
        ];
    }
}
