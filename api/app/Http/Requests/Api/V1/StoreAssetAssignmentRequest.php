<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetAssignmentRequest extends FormRequest
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
            'asset_id' => 'required|exists:assets,id',
            'user_id' => 'nullable|exists:users,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'assigned_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}
