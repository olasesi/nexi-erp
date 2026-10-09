<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGymMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Status is deliberately not editable here: suspension and reactivation go
     * through their own endpoints so every change is recorded.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'plan_id' => 'sometimes|exists:gym_plans,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'start_at' => 'sometimes|date',
            'renews_at' => 'sometimes|date',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
