<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DebitNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDebitNoteRequest extends FormRequest
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
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'debit_note_number' => 'required|string',
            'issue_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(DebitNote::STATUSES)],
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
