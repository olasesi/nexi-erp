<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $expenseId = $this->route('expense');

        return [
            'company_id' => 'nullable|exists:companies,id',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'reference_no' => 'nullable|string|max:50|unique:expenses,reference_no,'.$expenseId,
            'amount' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ];
    }
}
