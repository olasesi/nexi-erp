<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'nullable|string|in:draft,confirmed,processing,shipped,delivered,cancelled,refunded',
            'payment_status' => 'nullable|string|in:pending,partial,paid,overdue,refunded',
            'currency' => 'nullable|string|max:3',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'order_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required_with:items|string|max:255',
            'items.*.product_sku' => 'nullable|string|max:100',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.tax_rate' => 'numeric|min:0|max:100',
            'items.*.discount_amount' => 'numeric|min:0',
        ];
    }
}
