<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DealActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDealActivityRequest extends FormRequest
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
            'deal_id' => 'required|exists:crm_deals,id',
            'user_id' => 'nullable|exists:users,id',
            'type' => ['nullable', Rule::in(DealActivity::TYPES)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'status' => ['nullable', Rule::in(DealActivity::STATUSES)],
        ];
    }
}
