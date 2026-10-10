<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BudgetLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetLineRequest extends FormRequest
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
            'budget_id' => 'required|exists:budgets,id',
            'category' => 'required|string',
            'type' => ['nullable', Rule::in(BudgetLine::TYPES)],
            'planned_amount' => 'nullable|numeric|min:0',
            'actual_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
