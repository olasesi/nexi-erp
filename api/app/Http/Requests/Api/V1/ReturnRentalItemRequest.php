<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RentalItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnRentalItemRequest extends FormRequest
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
            'return_condition' => ['nullable', Rule::in(RentalItem::CONDITIONS)],
        ];
    }
}
