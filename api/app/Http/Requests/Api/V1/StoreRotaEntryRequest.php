<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreRotaEntryRequest extends FormRequest
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
            'rota_id' => 'required|exists:rotas,id',
            'employee_id' => 'nullable|exists:hrm_employees,id',
            'shift_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'shift_type' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
