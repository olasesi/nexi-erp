<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in(Form::STATUSES)],
            'is_template' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ];
    }
}
