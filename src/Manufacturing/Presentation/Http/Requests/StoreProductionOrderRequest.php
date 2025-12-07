<?php

namespace TmrEcosystem\Manufacturing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = Auth::user()->company_id;

        return [
            'item_uuid' => [
                'required',
                'uuid',
                Rule::exists('inventory_items', 'uuid')->where('company_id', $companyId)
            ],
            'planned_quantity' => ['required', 'numeric', 'min:1'],
            'planned_start_date' => ['required', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
        ];
    }
}
