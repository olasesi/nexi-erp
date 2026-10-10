<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoalRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'type' => ['sometimes', Rule::in(Goal::TYPES)],
            'category' => 'sometimes|nullable|string',
            'owner_id' => 'sometimes|nullable|exists:users,id',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'status' => ['sometimes', Rule::in(Goal::STATUSES)],
            'target_value' => 'sometimes|nullable|numeric|min:0',
            'current_value' => 'sometimes|nullable|numeric|min:0',
            'unit' => 'sometimes|nullable|string',
        ];
    }
}
