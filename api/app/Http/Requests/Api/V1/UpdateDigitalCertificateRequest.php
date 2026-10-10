<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DigitalCertificate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDigitalCertificateRequest extends FormRequest
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
            'certificate_number' => 'sometimes|nullable|string|max:255',
            'certificate_type' => 'sometimes|nullable|string|max:255',
            'issued_date' => 'sometimes|nullable|date',
            'expiry_date' => 'sometimes|nullable|date|after_or_equal:issued_date',
            'status' => ['sometimes', Rule::in(DigitalCertificate::STATUSES)],
            'file_url' => 'sometimes|nullable|url',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
