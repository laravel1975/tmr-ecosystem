<?php

namespace TmrEcosystem\Customers\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('customers')->ignore($this->customer)],
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:20',
            'credit_limit' => 'required|numeric|min:0',
            'credit_term_days' => 'required|integer|min:0',
            'is_credit_hold' => 'boolean',
        ];
    }
}
