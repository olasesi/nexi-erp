<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRateRequest extends FormRequest
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
            'base_currency' => 'nullable|string|size:3',
            'currency' => 'nullable|string|size:3|different:base_currency',
            'rate' => 'nullable|numeric|gt:0',
        ];
    }
}
