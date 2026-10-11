<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BookingResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingResourceRequest extends FormRequest
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
            'company_id' => ['sometimes', 'nullable', 'exists:companies,id'],
            'name' => ['sometimes', 'required', 'string'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(BookingResource::TYPES)],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
