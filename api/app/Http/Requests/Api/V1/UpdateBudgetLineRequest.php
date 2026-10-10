<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BudgetLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetLineRequest extends FormRequest
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
            'budget_id' => 'sometimes|exists:budgets,id',
            'category' => 'sometimes|string',
            'type' => ['sometimes', Rule::in(BudgetLine::TYPES)],
            'planned_amount' => 'sometimes|numeric|min:0',
            'actual_amount' => 'sometimes|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
