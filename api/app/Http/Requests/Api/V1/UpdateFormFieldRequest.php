<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormFieldRequest extends FormRequest
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
            'form_id' => 'sometimes|exists:forms,id',
            'label' => 'sometimes|string',
            'name' => 'sometimes|string|max:255',
            'type' => ['sometimes', Rule::in(FormField::TYPES)],
            'placeholder' => 'sometimes|nullable|string',
            'options' => 'sometimes|array',
            'is_required' => 'sometimes|boolean',
            'position' => 'sometimes|integer|min:0',
        ];
    }
}
