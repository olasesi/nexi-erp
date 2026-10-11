<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTodoRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'priority' => ['sometimes', Rule::in(Todo::PRIORITIES)],
            'status' => ['sometimes', Rule::in(Todo::STATUSES)],
            'due_date' => 'sometimes|nullable|date',
        ];
    }
}
