<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLaundryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The status is moved through the advance/cancel actions, never a plain
     * update, so the garment flow cannot be edited out of order.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'requested_at' => 'sometimes|nullable|date',
            'discount_amount' => 'sometimes|numeric|min:0',
            'tax_rate' => 'sometimes|numeric|min:0|max:100',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
