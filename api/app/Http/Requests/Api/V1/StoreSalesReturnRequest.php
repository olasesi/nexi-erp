<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SalesReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesReturnRequest extends FormRequest
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
            'contact_id' => 'nullable|exists:contacts,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'return_number' => 'required|string|max:255',
            'return_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(SalesReturn::STATUSES)],
            'reason' => 'nullable|string',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
