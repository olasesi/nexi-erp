<?php

namespace App\Http\Requests\Api\V1;

use App\Support\IntegrationGroupDefinitions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules are derived from the provider's catalog definition so only the
     * fields it actually declares (plus the `enabled` toggle) are accepted.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $provider = (string) $this->route('provider');

        /** @var array{
         *     categories: array<string, string>,
         *     providers: array<string, array{
         *         category: string,
         *         label: string,
         *         description?: string,
         *         fields: array<string, string|array{label?: string, type: string, default?: mixed, options?: array<int, string>}>
         *     }>
         * } $catalog
         */
        $catalog = config('business-integrations', []);

        $group = IntegrationGroupDefinitions::groups($catalog)[$provider] ?? null;

        $rules = ['enabled' => ['sometimes', 'boolean']];

        if ($group === null) {
            return $rules;
        }

        foreach ($group['keys'] as $name => $definition) {
            if ($name === 'enabled') {
                continue;
            }

            $rules[$name] = $this->rulesFor($definition);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<mixed>
     */
    private function rulesFor(array $definition): array
    {
        $type = (string) ($definition['type'] ?? 'string');

        return match ($type) {
            'boolean' => ['sometimes', 'boolean'],
            'integer' => ['sometimes', 'nullable', 'integer'],
            'url' => ['sometimes', 'nullable', 'url'],
            'email' => ['sometimes', 'nullable', 'email'],
            'select' => ['sometimes', 'nullable', 'string', Rule::in($definition['options'] ?? [])],
            default => ['sometimes', 'nullable', 'string'],
        };
    }
}
