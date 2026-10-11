<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesReturnItemRequest extends FormRequest
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
            'company_id' => 'sometimes|exists:companies,id',
            'sales_return_id' => 'sometimes|exists:sales_returns,id',
            'product_id' => 'sometimes|nullable|exists:products,id',
            'description' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|numeric|min:0',
            'unit_price' => 'sometimes|nullable|numeric|min:0',
            'total' => 'sometimes|nullable|numeric|min:0',
        ];
    }
}
