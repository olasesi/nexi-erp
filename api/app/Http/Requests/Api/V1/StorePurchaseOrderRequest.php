<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'nullable|string|in:draft,confirmed,processing,delivered,cancelled',
            'payment_status' => 'nullable|string|in:pending,partial,paid,overdue,refunded',
            'currency' => 'nullable|string|max:3',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.product_sku' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'numeric|min:0|max:100',
            'items.*.discount_amount' => 'numeric|min:0',
        ];
    }
}
