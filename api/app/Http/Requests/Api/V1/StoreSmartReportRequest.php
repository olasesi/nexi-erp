<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SmartReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSmartReportRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source' => ['required', Rule::in(SmartReport::SOURCES)],
            'columns' => 'nullable|array',
            'filters' => 'nullable|array',
            'group_by' => 'nullable|string',
            'aggregate' => ['nullable', Rule::in(SmartReport::AGGREGATES)],
            'aggregate_field' => 'nullable|string',
            'chart_type' => ['nullable', Rule::in(SmartReport::CHART_TYPES)],
            'is_shared' => 'nullable|boolean',
        ];
    }
}
