<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DebitNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDebitNoteRequest extends FormRequest
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
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'purchase_order_id' => 'sometimes|nullable|exists:purchase_orders,id',
            'debit_note_number' => 'sometimes|required|string',
            'issue_date' => 'sometimes|nullable|date',
            'status' => ['sometimes', Rule::in(DebitNote::STATUSES)],
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
