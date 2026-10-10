<?php

namespace App\Http\Requests\Api\V1;

use App\Models\GoalKeyResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalKeyResultRequest extends FormRequest
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
            'goal_id' => 'required|exists:goals,id',
            'title' => 'required|string',
            'target_value' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string',
            'status' => ['nullable', Rule::in(GoalKeyResult::STATUSES)],
        ];
    }
}
