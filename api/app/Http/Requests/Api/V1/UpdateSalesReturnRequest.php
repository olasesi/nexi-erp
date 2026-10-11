<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SalesReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesReturnRequest extends FormRequest
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
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'invoice_id' => 'sometimes|nullable|exists:invoices,id',
            'return_number' => 'sometimes|string|max:255',
            'return_date' => 'sometimes|nullable|date',
            'status' => ['sometimes', Rule::in(SalesReturn::STATUSES)],
            'reason' => 'sometimes|nullable|string',
            'subtotal' => 'sometimes|nullable|numeric|min:0',
            'tax_amount' => 'sometimes|nullable|numeric|min:0',
            'total' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
