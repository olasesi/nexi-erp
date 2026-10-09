<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePosRegisterRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|nullable|string|max:50',
            'warehouse_id' => 'sometimes|nullable|exists:warehouses,id',
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
