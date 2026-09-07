<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'opening_balance' => 'numeric',
            'closing_balance' => 'required|numeric',
            'transaction_ids' => 'array',
            'transaction_ids.*' => 'exists:bank_transactions,id',
        ];
    }
}
