<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'nullable|exists:companies,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ];
    }
}
