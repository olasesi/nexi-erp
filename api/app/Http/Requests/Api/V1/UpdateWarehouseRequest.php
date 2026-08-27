<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseId = $this->route('warehouse');

        return [
            'company_id' => 'nullable|exists:companies,id',
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50|unique:warehouses,code,' . $warehouseId,
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
