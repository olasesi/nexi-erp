<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimesheetEntryRequest extends FormRequest
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
            'timesheet_id' => 'required|exists:timesheets,id',
            'work_date' => 'required|date',
            'hours' => 'required|numeric|min:0',
            'project' => 'nullable|string|max:255',
            'task' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
