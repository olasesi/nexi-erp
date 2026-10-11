<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerGroupRequest extends FormRequest
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
            'company_id' => 'sometimes|exists:companies,id',
            'name' => 'sometimes|string',
            'description' => 'sometimes|nullable|string',
            'discount_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
