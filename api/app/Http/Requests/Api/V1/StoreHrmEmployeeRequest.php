<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HrmEmployee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrmEmployeeRequest extends FormRequest
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

        $employeeCode = ['required', 'string', 'max:50'];

        // Codes only need to be unique inside the company they are created
        // for, which mirrors the (company_id, employee_code) index.
        if ($companyId !== null) {
            $employeeCode[] = Rule::unique('hrm_employees', 'employee_code')
                ->where('company_id', $companyId);
        }

        return [
            'company_id' => 'nullable|exists:companies,id',
            'employee_code' => $employeeCode,
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'user_id' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:hrm_departments,id',
            'designation_id' => 'nullable|exists:hrm_designations,id',
            'manager_id' => 'nullable|exists:hrm_employees,id',
            'date_of_birth' => 'nullable|date',
            'date_of_joining' => 'nullable|date',
            'employment_status' => ['nullable', Rule::in(HrmEmployee::STATUSES)],
            'salary' => 'nullable|numeric|min:0|max:99999999999999.99',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
