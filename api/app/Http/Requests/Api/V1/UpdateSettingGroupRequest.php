<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    /**
     * Get the value that should be validated.
     */
    public function rules(): array
    {
        $group = $this->route('group');
        $definition = config("settings.groups.{$group}", null);

        if ($definition === null || ! isset($definition['keys'])) {
            return ['group' => ['prohibited']];
        }

        $rules = [];

        foreach ($definition['keys'] as $key => $keyRules) {
            $rules[$key] = array_merge(['nullable'], $keyRules);
        }

        return $rules;
    }

    /**
     * Reject keys that are not part of the settings group definition.
     */
    public function withValidator(Validator $validator): void
    {
        $group = $this->route('group');
        $definition = config("settings.groups.{$group}", null);

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
