<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CompanyPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyPolicyRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'version' => 'nullable|string|max:255',
            'effective_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(CompanyPolicy::STATUSES)],
        ];
    }
}
