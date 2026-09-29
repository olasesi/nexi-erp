<?php

namespace App\Http\Requests\Api\V1;

use App\Services\WebhookService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWebhookEndpointRequest extends FormRequest
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
            'name' => 'required|string|max:120',
            'url' => 'required|string|max:500|url',
            'events' => 'required|array|min:1',
            'events.*' => ['string', Rule::in(WebhookService::events())],
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:255',
        ];
    }

    /**
     * Plain HTTP is only tolerated for loopback targets, which keeps local
     * development possible while refusing cleartext calls to the internet.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = (string) $this->input('url');

            if ($url === '' || str_starts_with($url, 'https://')) {
                return;
            }

            $host = (string) parse_url($url, PHP_URL_HOST);

            if (! in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
                $validator->errors()->add('url', 'Webhook URLs must use HTTPS.');
            }
        });
    }
}
