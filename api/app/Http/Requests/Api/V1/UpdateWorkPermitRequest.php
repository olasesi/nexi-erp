<?php

namespace App\Http\Requests\Api\V1;

use App\Models\WorkPermit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkPermitRequest extends FormRequest
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
            'permit_number' => 'sometimes|string|max:255',
            'permit_type' => 'sometimes|nullable|string|max:255',
            'issuing_country' => 'sometimes|nullable|string|max:255',
            'issuing_authority' => 'sometimes|nullable|string|max:255',
            'issued_date' => 'sometimes|nullable|date',
            'expiry_date' => 'sometimes|nullable|date|after_or_equal:issued_date',
            'status' => ['sometimes', Rule::in(WorkPermit::STATUSES)],
            'document_url' => 'sometimes|nullable|url',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
