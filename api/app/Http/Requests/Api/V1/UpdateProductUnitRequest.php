<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductUnitRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'short_name' => 'sometimes|nullable|string|max:255',
            'base_unit' => 'sometimes|nullable|string|max:255',
            'operator' => ['sometimes', Rule::in(ProductUnit::OPERATORS)],
            'operation_value' => 'sometimes|nullable|numeric|min:0',
            'is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
