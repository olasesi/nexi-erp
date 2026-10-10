<?php

namespace App\Http\Requests\Api\V1;

use App\Models\WorkPermit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkPermitRequest extends FormRequest
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
            'permit_number' => 'required|string|max:255',
            'permit_type' => 'nullable|string|max:255',
            'issuing_country' => 'nullable|string|max:255',
            'issuing_authority' => 'nullable|string|max:255',
            'issued_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issued_date',
            'status' => ['nullable', Rule::in(WorkPermit::STATUSES)],
            'document_url' => 'nullable|url',
            'notes' => 'nullable|string',
        ];
    }
}
