<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|in:receipt,payment',
            'contact_id' => 'nullable|exists:contacts,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'payment_date' => 'sometimes|date',
            'amount' => 'nullable|numeric|min:0.01',
            'method' => 'nullable|in:cash,bank_transfer,credit_card,check,other',
            'status' => 'nullable|in:pending,completed,failed,refunded',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ];
    }
}
