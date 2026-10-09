<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRentalAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Status is intentionally absent: activation, cancellation and returns are
     * one-way transitions with their own endpoints.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date',
            'deposit_amount' => 'sometimes|numeric|min:0',
            'late_fee_per_day' => 'sometimes|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
