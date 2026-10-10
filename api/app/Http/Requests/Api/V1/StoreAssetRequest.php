<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreAssetRequest extends FormRequest
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
            'asset_category_id' => 'nullable|exists:asset_categories,id',
            'asset_tag' => ['required', 'string', 'max:255', $this->assetTagRule()],
            'name' => 'required|string|max:255',
            'serial_number' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'condition' => ['nullable', Rule::in(Asset::CONDITIONS)],
            'status' => ['nullable', Rule::in(Asset::STATUSES)],
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }

    protected function assetTagRule(): Unique
    {
        return Rule::unique('assets', 'asset_tag')->where('company_id', $this->assetTagCompanyId());
    }

    protected function assetTagCompanyId(): int
    {
        return (int) ($this->input('company_id') ?? $this->user()?->company_id);
    }
}
