<?php

namespace App\Http\Requests\Api\V1;

use App\Models\GymPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGymPlanRequest extends FormRequest
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
            'billing_period' => ['required', Rule::in(GymPlan::PERIODS)],
            'price' => 'required|numeric|min:0',
            'join_fee' => 'nullable|numeric|min:0',
            'max_monthly_visits' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }
}
