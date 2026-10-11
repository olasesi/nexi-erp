<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CreditNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditNoteRequest extends FormRequest
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
            'company_id' => 'sometimes|exists:companies,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'sales_return_id' => 'sometimes|nullable|exists:sales_returns,id',
            'credit_note_number' => 'sometimes|string|max:255',
            'issue_date' => 'sometimes|nullable|date',
            'status' => ['sometimes', Rule::in(CreditNote::STATUSES)],
            'reason' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
            'subtotal' => 'sometimes|nullable|numeric|min:0',
            'tax_amount' => 'sometimes|nullable|numeric|min:0',
            'total' => 'sometimes|nullable|numeric|min:0',
            'applied_amount' => 'sometimes|nullable|numeric|min:0',
            'balance' => 'sometimes|nullable|numeric|min:0',
        ];
    }
}
