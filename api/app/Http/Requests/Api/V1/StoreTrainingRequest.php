<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Training;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingRequest extends FormRequest
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
            'training_type_id' => 'nullable|exists:training_types,id',
            'title' => 'required|string|max:255',
            'trainer' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => ['nullable', Rule::in(Training::STATUSES)],
            'description' => 'nullable|string',
        ];
    }
}
