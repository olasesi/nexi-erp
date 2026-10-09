<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CoworkingUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoworkingUnitRequest extends FormRequest
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
            'type' => ['required', Rule::in(CoworkingUnit::TYPES)],
            'capacity' => 'required|integer|min:1|max:500',
            'rate_hourly' => 'nullable|numeric|min:0',
            'rate_daily' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
