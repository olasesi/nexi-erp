<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CompanyPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyPolicyRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|nullable|string',
            'version' => 'sometimes|nullable|string|max:255',
            'effective_date' => 'sometimes|nullable|date',
            'status' => ['sometimes', 'nullable', Rule::in(CompanyPolicy::STATUSES)],
        ];
    }
}
