<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ImportBankTransactionsRequest extends FormRequest
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
            'transactions' => 'required|array|min:1',
            'transactions.*.transaction_date' => 'required|date',
            'transactions.*.description' => 'required|string|max:255',
            'transactions.*.reference' => 'nullable|string|max:100',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.category' => 'nullable|string|max:100',
        ];
    }
}
