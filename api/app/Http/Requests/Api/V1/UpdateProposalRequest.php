<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProposalRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'status' => ['sometimes', Rule::in(Proposal::STATUSES)],
            'issue_date' => 'sometimes|nullable|date',
            'valid_until' => 'sometimes|nullable|date|after_or_equal:issue_date',
            'currency' => 'sometimes|nullable|string|size:3',
            'subtotal' => 'sometimes|nullable|numeric|min:0',
            'tax_amount' => 'sometimes|nullable|numeric|min:0',
            'discount_amount' => 'sometimes|nullable|numeric|min:0',
            'total' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
            'terms' => 'sometimes|nullable|string',
        ];
    }
}
