<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'period_type' => ['sometimes', Rule::in(Budget::PERIODS)],
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'status' => ['sometimes', Rule::in(Budget::STATUSES)],
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
