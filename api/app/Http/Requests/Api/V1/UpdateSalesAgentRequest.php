<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SalesAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesAgentRequest extends FormRequest
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
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string|max:255',
            'commission_rate' => 'sometimes|nullable|numeric|min:0',
            'status' => ['sometimes', Rule::in(SalesAgent::STATUSES)],
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
