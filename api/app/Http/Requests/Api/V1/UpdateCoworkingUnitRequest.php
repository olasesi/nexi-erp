<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CoworkingUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoworkingUnitRequest extends FormRequest
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
            'type' => ['sometimes', Rule::in(CoworkingUnit::TYPES)],
            'capacity' => 'sometimes|integer|min:1|max:500',
            'rate_hourly' => 'sometimes|nullable|numeric|min:0',
            'rate_daily' => 'sometimes|nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
