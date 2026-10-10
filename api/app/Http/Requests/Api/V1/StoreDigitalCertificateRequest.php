<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DigitalCertificate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDigitalCertificateRequest extends FormRequest
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
            'certificate_number' => 'nullable|string|max:255',
            'certificate_type' => 'nullable|string|max:255',
            'issued_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issued_date',
            'status' => ['nullable', Rule::in(DigitalCertificate::STATUSES)],
            'file_url' => 'nullable|url',
            'notes' => 'nullable|string',
        ];
    }
}
