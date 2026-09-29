<?php

namespace App\Http\Requests\Api\V1;

use App\Services\WebhookService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWebhookEndpointRequest extends StoreWebhookEndpointRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'nullable|exists:companies,id',
            'name' => 'sometimes|required|string|max:120',
            'url' => 'sometimes|required|string|max:500|url',
            'events' => 'sometimes|required|array|min:1',
            'events.*' => ['string', Rule::in(WebhookService::events())],
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $url = $this->input('url');

        if ($url === null) {
            return;
        }

        parent::withValidator($validator);
    }
}
