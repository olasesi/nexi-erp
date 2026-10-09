<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HrmEmployee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrmEmployeeRequest extends FormRequest
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
        $companyId = $this->input('company_id') ?? auth('api')->user()?->company_id;
        $employeeId = $this->route('hrm_employee');

        $employeeCode = ['sometimes', 'string', 'max:50'];

        if ($companyId !== null) {
            $employeeCode[] = Rule::unique('hrm_employees', 'employee_code')
                ->ignore($employeeId)
                ->where('company_id', $companyId);
        }

        return [
            'employee_code' => $employeeCode,
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'user_id' => 'sometimes|nullable|exists:users,id',
            'department_id' => 'sometimes|nullable|exists:hrm_departments,id',
            'designation_id' => 'sometimes|nullable|exists:hrm_designations,id',
            'manager_id' => 'sometimes|nullable|exists:hrm_employees,id',
            'date_of_birth' => 'sometimes|nullable|date',
            'date_of_joining' => 'sometimes|nullable|date',
            'employment_status' => ['sometimes', 'nullable', Rule::in(HrmEmployee::STATUSES)],
            'salary' => 'sometimes|nullable|numeric|min:0|max:99999999999999.99',
            'address' => 'sometimes|nullable|string|max:500',
            'notes' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
