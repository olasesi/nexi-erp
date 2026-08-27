<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'nullable|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100',
            'type' => 'required|string|in:product,service,digital,bundle',
            'unit' => 'nullable|string|max:50',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'tax_rate' => 'numeric|min:0|max:100',
            'stock_quantity' => 'integer|min:0',
            'min_stock_level' => 'integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
