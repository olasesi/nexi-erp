<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $incomeId = $this->route('income');

        return [
            'company_id' => 'nullable|exists:companies,id',
            'income_category_id' => 'nullable|exists:income_categories,id',
            'reference_no' => 'nullable|string|max:50|unique:incomes,reference_no,'.$incomeId,
            'amount' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ];
    }
}
