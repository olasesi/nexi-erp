<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HrmAttendance;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrmAttendanceRequest extends FormRequest
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
        $attendance = HrmAttendance::query()
            ->whereKey($this->route('hrm_attendance'))
            ->first();
        $employeeId = $this->input('employee_id') ?? $attendance?->employee_id;

        $dateRules = ['sometimes', 'date'];

        if ($employeeId !== null) {
            $ignore = $this->route('hrm_attendance');

            $dateRules[] = function (string $attribute, mixed $value, Closure $fail) use ($employeeId, $ignore): void {
                $query = HrmAttendance::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('attendance_date', (string) $value);

                if ($ignore !== null) {
                    $query->whereKeyNot($ignore);
                }

                if ($query->exists()) {
                    $fail('This employee already has an attendance record for that date.');
                }
            };
        }

        return [
            'employee_id' => 'sometimes|nullable|exists:hrm_employees,id',
            'attendance_date' => $dateRules,
            'status' => ['sometimes', 'nullable', Rule::in(HrmAttendance::STATUSES)],
            'check_in' => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'check_out' => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'work_hours' => 'sometimes|nullable|numeric|min:0|max:24',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
