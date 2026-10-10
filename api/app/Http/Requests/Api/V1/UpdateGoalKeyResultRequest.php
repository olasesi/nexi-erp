<?php

namespace App\Http\Requests\Api\V1;

use App\Models\GoalKeyResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoalKeyResultRequest extends FormRequest
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
            'goal_id' => 'sometimes|exists:goals,id',
            'title' => 'sometimes|string',
            'target_value' => 'sometimes|nullable|numeric|min:0',
            'current_value' => 'sometimes|nullable|numeric|min:0',
            'unit' => 'sometimes|nullable|string',
            'status' => ['sometimes', Rule::in(GoalKeyResult::STATUSES)],
        ];
    }
}
