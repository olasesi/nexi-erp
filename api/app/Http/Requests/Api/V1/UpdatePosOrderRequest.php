<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PosOrderStatus;
use App\Models\PosPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePosOrderRequest extends FormRequest
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
            'register_id' => 'sometimes|nullable|exists:pos_registers,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'cashier_id' => 'sometimes|nullable|exists:users,id',
            'status' => ['sometimes', Rule::enum(PosOrderStatus::class)],
            'currency' => 'sometimes|string|max:3',
            'discount_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'notes' => 'sometimes|nullable|string',
            'order_date' => 'sometimes|nullable|date',
            'items' => 'sometimes|array|min:1',
            'items.*' => [
                function (string $attribute, mixed $value, $fail): void {
                    if (empty($value['product_id']) && empty($value['product_name'])) {
                        $fail('An item needs a product_id or a product_name.');
                    }
                },
            ],
            'items.*.id' => 'sometimes|integer|exists:pos_order_items,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.product_sku' => 'nullable|string|max:100',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'payments' => 'sometimes|nullable|array',
            'payments.*.method' => ['required_with:payments', Rule::in(PosPayment::METHODS)],
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
            'payments.*.paid_at' => 'nullable|date',
            'payments.*.notes' => 'nullable|string',
        ];
    }
}
