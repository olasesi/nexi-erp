<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormFieldRequest extends FormRequest
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
            'form_id' => 'required|exists:forms,id',
            'label' => 'required|string',
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(FormField::TYPES)],
            'placeholder' => 'nullable|string',
            'options' => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'position' => 'nullable|integer|min:0',
        ];
    }
}
