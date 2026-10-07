<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'name' => 'sometimes|string|max:100',
            'position' => 'sometimes|integer|min:0',
        ];
    }
}
