<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessLocationRequest extends FormRequest
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
            'name' => 'nullable|string|max:255',
            'location_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('business_locations', 'location_id')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($this->route('business_location')),
            ],
            'landmark' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'price_group' => 'nullable|string|max:50',
            'invoice_scheme' => 'nullable|string|max:50',
            'invoice_layout_for_pos' => 'nullable|string|max:50',
            'invoice_layout_for_sale' => 'nullable|string|max:50',
        ];
    }
}
