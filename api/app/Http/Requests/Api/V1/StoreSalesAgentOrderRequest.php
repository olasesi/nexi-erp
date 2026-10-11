<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SalesAgentOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesAgentOrderRequest extends FormRequest
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
            'sales_agent_id' => 'required|exists:sales_agents,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'order_number' => 'required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'commission_amount' => 'nullable|numeric|min:0',
            'status' => ['nullable', Rule::in(SalesAgentOrder::STATUSES)],
            'notes' => 'nullable|string',
        ];
    }
}
