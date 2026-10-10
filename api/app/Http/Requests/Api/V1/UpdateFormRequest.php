<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => ['sometimes', Rule::in(Form::STATUSES)],
            'is_template' => 'sometimes|boolean',
            'settings' => 'sometimes|array',
        ];
    }
}
