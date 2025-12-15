<?php

namespace TmrEcosystem\Customers\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:customers,code',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:20',
            // Financial
            'credit_limit' => 'required|numeric|min:0',
            'credit_term_days' => 'required|integer|min:0',
            'is_credit_hold' => 'boolean',
        ];
    }
}
