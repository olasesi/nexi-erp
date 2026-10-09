<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ProjectTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectTaskRequest extends FormRequest
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
            'company_id' => 'nullable|exists:companies,id',
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => ['sometimes', Rule::in(ProjectTask::STATUSES)],
            'priority' => ['sometimes', Rule::in(ProjectTask::PRIORITIES)],
            'due_date' => 'nullable|date',
            'completed_at' => 'nullable|date',
        ];
    }
}
