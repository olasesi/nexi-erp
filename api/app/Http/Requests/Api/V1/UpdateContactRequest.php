<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'nullable|exists:companies,id',
            'type' => 'nullable|string|in:customer,supplier,lead,both',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
