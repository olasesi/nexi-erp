<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PosOrderStatus;
use App\Models\PosPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosOrderRequest extends FormRequest
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
            'register_id' => 'nullable|exists:pos_registers,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'cashier_id' => 'nullable|exists:users,id',
            'status' => ['sometimes', Rule::enum(PosOrderStatus::class)],
            'currency' => 'nullable|string|max:3',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'order_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*' => [
                function (string $attribute, mixed $value, $fail): void {
                    if (empty($value['product_id']) && empty($value['product_name'])) {
                        $fail('An item needs a product_id or a product_name.');
                    }
                },
            ],
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.product_sku' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.method' => ['required_with:payments', Rule::in(PosPayment::METHODS)],
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
            'payments.*.paid_at' => 'nullable|date',
            'payments.*.notes' => 'nullable|string',
        ];
    }
}
