<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SalesAgentOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesAgentOrderRequest extends FormRequest
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
            'sales_agent_id' => 'sometimes|exists:sales_agents,id',
            'sales_order_id' => 'sometimes|nullable|exists:sales_orders,id',
            'order_number' => 'sometimes|string|max:255',
            'customer_name' => 'sometimes|nullable|string|max:255',
            'amount' => 'sometimes|nullable|numeric|min:0',
            'commission_amount' => 'sometimes|nullable|numeric|min:0',
            'status' => ['sometimes', Rule::in(SalesAgentOrder::STATUSES)],
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
