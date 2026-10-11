<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductUnitRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'base_unit' => 'nullable|string|max:255',
            'operator' => ['nullable', Rule::in(ProductUnit::OPERATORS)],
            'operation_value' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
