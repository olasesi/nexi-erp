<?php

namespace App\Http\Requests\Api\V1;

use App\Services\BusinessSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBusinessSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    /**
     * Validation rules derived from the typed key definitions.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = (string) $this->route('group');
        $definition = config("business-settings.groups.{$group}", null);

        if ($definition === null || ! isset($definition['keys'])) {
            return ['group' => ['prohibited']];
        }

        $settings = app(BusinessSettingsService::class);
        $rules = [];

        foreach ($definition['keys'] as $key => $keyDefinition) {
            $type = $keyDefinition['type'] ?? 'string';
            $keyRules = ['nullable'];

            switch ($type) {
                case 'integer':
                    $keyRules[] = 'integer';
                    break;
                case 'decimal':
                    $keyRules[] = 'numeric';
                    break;
                case 'date':
                    $keyRules[] = 'date';
                    break;
                case 'url':
                    $keyRules[] = 'url';
                    break;
                case 'email':
                    $keyRules[] = 'email';
                    break;
                case 'boolean':
                    $keyRules[] = 'boolean';
                    break;
                case 'select':
                    $keyRules[] = 'string';
                    $keyRules[] = Rule::in(array_keys($settings->optionsFor($group, $key)));
                    break;
                case 'multi_select':
                    $keyRules[] = 'array';
                    $rules["{$key}.*"] = [Rule::in(array_keys($settings->optionsFor($group, $key)))];
                    break;
                case 'array':
                case 'object':
                    $keyRules[] = 'array';
                    break;
                default:
                    $keyRules[] = 'string';
            }

            foreach ($keyDefinition['rules'] ?? [] as $extra) {
                $keyRules[] = $extra;
            }

            $rules[$key] = $keyRules;
        }

        return $rules;
    }

    /**
     * Reject keys that are not part of the group definition.
     */
    public function withValidator(Validator $validator): void
    {
        $group = (string) $this->route('group');
        $definition = config("business-settings.groups.{$group}", null);

        if ($definition === null || ! isset($definition['keys'])) {
            return;
        }

        $unknown = array_diff(
            array_keys($this->all()),
            array_merge(array_keys($definition['keys']), array_keys($this->query()))
        );

        if ($unknown !== []) {
            $validator->after(function (Validator $validator) use ($unknown) {
                foreach ($unknown as $key) {
                    $validator->errors()->add($key, "The field {$key} is not a valid setting for this group.");
                }
            });
        }
    }
}
