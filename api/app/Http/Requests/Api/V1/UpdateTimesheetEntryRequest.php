<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimesheetEntryRequest extends FormRequest
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
            'timesheet_id' => 'sometimes|exists:timesheets,id',
            'work_date' => 'sometimes|date',
            'hours' => 'sometimes|numeric|min:0',
            'project' => 'sometimes|nullable|string|max:255',
            'task' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
