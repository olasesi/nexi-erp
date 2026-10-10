<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateAssetRequest extends FormRequest
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
            'asset_category_id' => 'sometimes|nullable|exists:asset_categories,id',
            'asset_tag' => ['sometimes', 'string', 'max:255', $this->assetTagRule()],
            'name' => 'sometimes|string|max:255',
            'serial_number' => 'sometimes|nullable|string',
            'purchase_date' => 'sometimes|nullable|date',
            'purchase_cost' => 'sometimes|nullable|numeric|min:0',
            'current_value' => 'sometimes|nullable|numeric|min:0',
            'condition' => ['sometimes', Rule::in(Asset::CONDITIONS)],
            'status' => ['sometimes', Rule::in(Asset::STATUSES)],
            'location' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
        ];
    }

    protected function assetTagRule(): Unique
    {
        return Rule::unique('assets', 'asset_tag')
            ->ignore($this->route('asset'))
            ->where('company_id', $this->assetTagCompanyId());
    }

    protected function assetTagCompanyId(): int
    {
        return (int) ($this->input('company_id') ?? $this->user()?->company_id);
    }
}
