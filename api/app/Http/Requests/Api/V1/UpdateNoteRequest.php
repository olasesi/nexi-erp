<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
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
            'company_id' => 'sometimes|exists:companies,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
            'title' => 'sometimes|string',
            'body' => 'sometimes|nullable|string',
            'is_pinned' => 'sometimes|nullable|boolean',
            'color' => 'sometimes|nullable|string',
        ];
    }
}
