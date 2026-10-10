<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Rota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRotaRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'period_start' => 'sometimes|nullable|date',
            'period_end' => 'sometimes|nullable|date|after_or_equal:period_start',
            'status' => ['sometimes', Rule::in(Rota::STATUSES)],
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
