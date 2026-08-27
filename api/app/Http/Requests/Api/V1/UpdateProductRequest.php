<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'company_id' => 'nullable|exists:companies,id',
            'category_id' => 'nullable|exists:product_categories,id',
            'name' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,'.$productId,
            'barcode' => 'nullable|string|max:100',
            'type' => 'nullable|string|in:product,service,digital,bundle',
            'unit' => 'nullable|string|max:50',
            'sale_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'min_stock_level' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
