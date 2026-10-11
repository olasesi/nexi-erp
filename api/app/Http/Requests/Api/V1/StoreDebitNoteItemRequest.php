<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebitNoteItemRequest extends FormRequest
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
            'debit_note_id' => 'required|exists:debit_notes,id',
            'product_id' => 'nullable|exists:products,id',
            'description' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
        ];
    }
}
