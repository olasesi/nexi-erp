<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Self-service profile edits only. Email is the login identity and has no
     * verification flow here, so it stays on /user for admins to manage.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($this->user()),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
