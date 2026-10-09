<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ProjectMilestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectMilestoneRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'due_date' => 'sometimes|nullable|date',
            'status' => ['sometimes', Rule::in(ProjectMilestone::STATUSES)],
            'reached_at' => 'sometimes|nullable|date',
        ];
    }
}
