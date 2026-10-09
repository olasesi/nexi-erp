<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RentalAgreement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalAgreementRequest extends FormRequest
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
            'start_at' => 'required|date',
            'end_at' => 'required|date|after_or_equal:start_at',
            'status' => ['nullable', Rule::in(RentalAgreement::STATUSES)],
            'deposit_amount' => 'nullable|numeric|min:0',
            'late_fee_per_day' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
