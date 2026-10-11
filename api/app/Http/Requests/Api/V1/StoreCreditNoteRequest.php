<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CreditNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends FormRequest
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
            'contact_id' => 'nullable|exists:contacts,id',
            'sales_return_id' => 'nullable|exists:sales_returns,id',
            'credit_note_number' => 'required|string|max:255',
            'issue_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(CreditNote::STATUSES)],
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'applied_amount' => 'nullable|numeric|min:0',
            'balance' => 'nullable|numeric|min:0',
        ];
    }
}
