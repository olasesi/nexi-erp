<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Commission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommissionRequest extends FormRequest
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
            'employee_id' => 'sometimes|nullable|exists:hrm_employees,id',
            'title' => 'sometimes|string|max:255',
            'reference' => 'sometimes|nullable|string|max:255',
            'sale_amount' => 'sometimes|nullable|numeric|min:0',
            'rate' => 'sometimes|nullable|numeric|min:0',
            'amount' => 'sometimes|nullable|numeric|min:0',
            'status' => ['sometimes', Rule::in(Commission::STATUSES)],
            'earned_on' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
