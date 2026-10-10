<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['nullable', Rule::in(Goal::TYPES)],
            'category' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => ['nullable', Rule::in(Goal::STATUSES)],
            'target_value' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string',
        ];
    }
}
