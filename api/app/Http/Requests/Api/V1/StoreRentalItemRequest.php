<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RentalItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalItemRequest extends FormRequest
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
            'rental_agreement_id' => 'required|exists:rental_agreements,id',
            'product_id' => 'nullable|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'billing_period' => ['required', Rule::in(RentalItem::PERIODS)],
        ];
    }
}
