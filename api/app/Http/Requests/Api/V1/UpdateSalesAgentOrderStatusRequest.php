<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SalesAgentOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesAgentOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(SalesAgentOrder::STATUSES)],
        ];
    }
}
