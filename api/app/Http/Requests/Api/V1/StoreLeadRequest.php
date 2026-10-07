<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'assigned_to' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'organization' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => ['nullable', Rule::in(Lead::SOURCES)],
            'status' => ['nullable', Rule::in(Lead::STATUSES)],
            'value' => 'nullable|numeric|min:0|max:99999999999999.99',
            'expected_close_date' => 'nullable|date',
            'next_follow_up_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}
