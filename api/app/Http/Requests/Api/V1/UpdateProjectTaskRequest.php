<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ProjectTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectTaskRequest extends FormRequest
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
            'project_id' => 'sometimes|exists:projects,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'status' => ['sometimes', Rule::in(ProjectTask::STATUSES)],
            'priority' => ['sometimes', Rule::in(ProjectTask::PRIORITIES)],
            'due_date' => 'sometimes|nullable|date',
            'completed_at' => 'sometimes|nullable|date',
        ];
    }
}
