<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Training;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingRequest extends FormRequest
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
            'company_id' => 'sometimes|exists:companies,id',
            'training_type_id' => 'sometimes|nullable|exists:training_types,id',
            'title' => 'sometimes|string|max:255',
            'trainer' => 'sometimes|nullable|string|max:255',
            'branch' => 'sometimes|nullable|string|max:255',
            'department' => 'sometimes|nullable|string|max:255',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'status' => ['sometimes', Rule::in(Training::STATUSES)],
            'description' => 'sometimes|nullable|string',
        ];
    }
}
