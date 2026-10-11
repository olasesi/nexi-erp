<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnItemRequest extends FormRequest
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
            'sales_return_id' => 'required|exists:sales_returns,id',
            'product_id' => 'nullable|exists:products,id',
            'description' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
        ];
    }
}
