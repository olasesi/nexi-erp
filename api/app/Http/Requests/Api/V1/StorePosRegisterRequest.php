<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePosRegisterRequest extends FormRequest
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
            'code' => 'nullable|string|max:50',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
