<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Commission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommissionRequest extends FormRequest
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
            'employee_id' => 'nullable|exists:hrm_employees,id',
            'title' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'sale_amount' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'status' => ['nullable', Rule::in(Commission::STATUSES)],
            'earned_on' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}
