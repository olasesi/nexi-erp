<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDebitNoteItemRequest extends FormRequest
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
            'debit_note_id' => 'sometimes|required|exists:debit_notes,id',
            'product_id' => 'sometimes|nullable|exists:products,id',
            'description' => 'sometimes|required|string',
            'quantity' => 'sometimes|required|numeric|min:0',
            'unit_price' => 'sometimes|nullable|numeric|min:0',
            'tax_rate' => 'sometimes|nullable|numeric|min:0',
            'total' => 'sometimes|nullable|numeric|min:0',
        ];
    }
}
