<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetCategoryRequest extends FormRequest
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
            'description' => 'nullable|string',
            'depreciation_rate' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
