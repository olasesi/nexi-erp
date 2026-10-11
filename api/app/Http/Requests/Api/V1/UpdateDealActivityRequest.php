<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DealActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDealActivityRequest extends FormRequest
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
            'deal_id' => 'sometimes|exists:crm_deals,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
            'type' => ['sometimes', Rule::in(DealActivity::TYPES)],
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'scheduled_at' => 'sometimes|nullable|date',
            'status' => ['sometimes', Rule::in(DealActivity::STATUSES)],
        ];
    }
}
