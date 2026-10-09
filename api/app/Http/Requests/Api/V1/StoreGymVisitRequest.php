<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreGymVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A visit is a check-in: the timestamp is always "now" and the operator is
     * taken from the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'nullable|exists:companies,id',
            'gym_member_id' => 'required|exists:gym_members,id',
        ];
    }
}
