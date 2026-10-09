<?php

namespace App\Http\Requests\Api\V1;

use App\Models\LaundryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLaundryItemRequest extends FormRequest
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
            'laundry_order_id' => 'required|exists:laundry_orders,id',
            'product_id' => 'nullable|exists:products,id',
            'description' => 'required|string|max:255',
            'service' => ['sometimes', Rule::in(LaundryItem::SERVICES)],
            'quantity' => 'required|integer|min:1|max:10000',
            'unit_price' => 'nullable|numeric|min:0',
            'weight_kg' => 'nullable|numeric|min:0',
        ];
    }
}
