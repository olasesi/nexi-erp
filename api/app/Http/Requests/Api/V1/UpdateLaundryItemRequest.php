<?php

namespace App\Http\Requests\Api\V1;

use App\Models\LaundryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLaundryItemRequest extends FormRequest
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
            'product_id' => 'sometimes|nullable|exists:products,id',
            'description' => 'sometimes|string|max:255',
            'service' => ['sometimes', Rule::in(LaundryItem::SERVICES)],
            'quantity' => 'sometimes|integer|min:1|max:10000',
            'unit_price' => 'sometimes|numeric|min:0',
            'weight_kg' => 'sometimes|nullable|numeric|min:0',
        ];
    }
}
