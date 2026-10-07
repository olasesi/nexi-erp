<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'lead_id' => 'nullable|exists:crm_leads,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'crm_stage_id' => 'required|exists:crm_stages,id',
            'assigned_to' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'value' => 'nullable|numeric|min:0|max:99999999999999.99',
            'status' => 'sometimes|in:open,won,lost',
            'close_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}
