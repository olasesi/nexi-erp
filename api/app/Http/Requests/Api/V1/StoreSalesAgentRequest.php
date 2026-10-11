<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SalesAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesAgentRequest extends FormRequest
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
            'contact_id' => 'nullable|exists:contacts,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:255',
            'commission_rate' => 'nullable|numeric|min:0',
            'status' => ['nullable', Rule::in(SalesAgent::STATUSES)],
            'notes' => 'nullable|string',
        ];
    }
}
