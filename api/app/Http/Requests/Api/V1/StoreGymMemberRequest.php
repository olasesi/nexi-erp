<?php

namespace App\Http\Requests\Api\V1;

use App\Models\GymMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGymMemberRequest extends FormRequest
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
            'plan_id' => 'required|exists:gym_plans,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'start_at' => 'required|date',
            'renews_at' => 'required|date|after_or_equal:start_at',
            'status' => ['nullable', Rule::in(GymMember::STATUSES)],
            'notes' => 'nullable|string',
        ];
    }
}
