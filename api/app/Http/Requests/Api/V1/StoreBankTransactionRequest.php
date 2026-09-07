<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankTransactionRequest extends FormRequest
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
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
            'amount' => 'required|numeric',
            'category' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,cleared',
        ];
    }
}
