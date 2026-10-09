<?php

namespace App\Http\Requests\Api\V1;

use App\Models\GymPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGymPlanRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'billing_period' => ['sometimes', Rule::in(GymPlan::PERIODS)],
            'price' => 'sometimes|numeric|min:0',
            'join_fee' => 'sometimes|nullable|numeric|min:0',
            'max_monthly_visits' => 'sometimes|nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
