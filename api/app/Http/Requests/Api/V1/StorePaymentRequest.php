<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'type' => 'required|in:receipt,payment',
            'contact_id' => 'required|exists:contacts,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|in:cash,bank_transfer,credit_card,check,other',
            'status' => 'nullable|in:pending,completed,failed,refunded',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ];
    }
}
