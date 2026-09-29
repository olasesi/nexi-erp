<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRateRequest extends FormRequest
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
            'company_id' => 'required|exists:companies,id',
            'base_currency' => 'nullable|string|size:3',
            'currency' => 'required|string|size:3|different:base_currency',
            'rate' => 'required|numeric|gt:0',
        ];
    }
}
