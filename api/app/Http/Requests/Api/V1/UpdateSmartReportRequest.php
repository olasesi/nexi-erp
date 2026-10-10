<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SmartReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSmartReportRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'source' => ['sometimes', Rule::in(SmartReport::SOURCES)],
            'columns' => 'sometimes|nullable|array',
            'filters' => 'sometimes|nullable|array',
            'group_by' => 'sometimes|nullable|string',
            'aggregate' => ['sometimes', 'nullable', Rule::in(SmartReport::AGGREGATES)],
            'aggregate_field' => 'sometimes|nullable|string',
            'chart_type' => ['sometimes', 'nullable', Rule::in(SmartReport::CHART_TYPES)],
            'is_shared' => 'sometimes|boolean',
        ];
    }
}
