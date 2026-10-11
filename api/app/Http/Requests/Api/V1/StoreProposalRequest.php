<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProposalRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'status' => ['nullable', Rule::in(Proposal::STATUSES)],
            'issue_date' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'nullable|string|size:3',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
        ];
    }
}
