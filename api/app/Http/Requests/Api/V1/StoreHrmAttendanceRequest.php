<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HrmAttendance;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrmAttendanceRequest extends FormRequest
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
        $employeeId = $this->input('employee_id');

        $dateRules = ['required', 'date'];

        // One row per employee per day, mirroring the (employee_id,
        // attendance_date) index. Compared by calendar day so the check holds
        // regardless of how the date is stored. Without an employee the
        // required rule already fails, so this would only add noise.
        if ($employeeId !== null) {
            $dateRules[] = function (string $attribute, mixed $value, Closure $fail) use ($employeeId): void {
                $duplicate = HrmAttendance::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('attendance_date', (string) $value)
                    ->exists();

                if ($duplicate) {
                    $fail('This employee already has an attendance record for that date.');
                }
            };
        }

        return [
            'company_id' => 'nullable|exists:companies,id',
            'employee_id' => 'required|exists:hrm_employees,id',
            'attendance_date' => $dateRules,
            'status' => ['nullable', Rule::in(HrmAttendance::STATUSES)],
            'check_in' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'check_out' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'work_hours' => 'nullable|numeric|min:0|max:24',
            'notes' => 'nullable|string',
        ];
    }
}
