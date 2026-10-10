<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetAssignmentRequest extends FormRequest
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
            'asset_id' => 'sometimes|exists:assets,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'assigned_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
