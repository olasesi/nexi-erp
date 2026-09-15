<?php

namespace App\Services;

use DateTimeZone;

class BusinessSettingsService
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * All business settings groups as typed values.
     *
     * @return array<string, array<string, mixed>>
     */
    public function values(?int $companyId = null): array
    {
        $values = [];

        foreach (array_keys($this->groups()) as $group) {
            $values[$group] = $this->group($companyId, $group);
        }

        return $values;
    }

    /**
     * A single business settings group as typed values.
     *
     * @return array<string, mixed>
     */
    public function group(?int $companyId, string $group): array
    {
        $rows = $this->settings->group($companyId, $group, SettingsService::BUSINESS_NAMESPACE);

        if ($rows === []) {
            return [];
        }

        $typed = [];

        foreach ($rows as $key => $value) {
            $typed[$key] = $this->typed($group, $key, $value);
        }

        return $typed;
    }

    /**
     * Persist a batch of keys for a business settings group.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(?int $companyId, string $group, array $values): void
    {
        $definition = $this->definition($group);

        if ($definition === null) {
            return;
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $definition['keys'])) {
                continue;
            }

            $normalized[$key] = $this->normalize($group, $key, $value);
        }

        $this->settings->set($companyId, $group, $normalized, SettingsService::BUSINESS_NAMESPACE);
    }

    /**
     * Read a single typed setting for a downstream consumer.
     */
    public function value(?int $companyId, string $group, string $key): mixed
    {
        $definition = $this->definition($group);

        if ($definition === null || ! isset($definition['keys'][$key])) {
            return null;
        }

        return $this->typed(
            $group,
            $key,
            $this->settings->group($companyId, $group, SettingsService::BUSINESS_NAMESPACE)[$key] ?? null
        );
    }

    /**
     * Group labels and descriptions to power the settings navigation.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public function meta(): array
    {
        $meta = [];

        foreach ($this->groups() as $group => $definition) {
            $meta[$group] = [
                'label' => $definition['label'] ?? ucwords(str_replace('_', ' ', $group)),
                'description' => $definition['description'] ?? '',
            ];
        }

        return $meta;
    }

    /**
     * Resolve selectable options for a key.
     *
     * @return array<string, string>
     */
    public function optionsFor(string $group, string $key): array
    {
        $definition = $this->definition($group);

        if ($definition === null || ! isset($definition['keys'][$key])) {
            return [];
        }

        $options = $definition['keys'][$key]['options'] ?? [];

        if (is_string($options)) {
            return match ($options) {
                'currencies' => $this->currencyOptions(),
                'timezones' => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()),
                default => config("business-settings.option_list.{$options}", []),
            };
        }

        if (array_is_list($options)) {
            return array_combine($options, $options);
        }

        return $options;
    }

    /**
     * Currency code => label pairs with the default currency first.
     *
     * @return array<string, string>
     */
    private function currencyOptions(): array
    {
        $currencies = config('currencies', []);

        uasort($currencies, fn (array $current, array $next) => ($next[4] ? 1 : 0) <=> ($current[4] ? 1 : 0));

        $options = [];

        foreach ($currencies as $currency) {
            $options[$currency[2]] = "{$currency[1]} ({$currency[2]})";
        }

        return $options;
    }

    /**
     * Cast a stored/default raw value to its typed representation.
     */
    protected function typed(string $group, string $key, mixed $value): mixed
    {
        $definition = $this->definition($group);
        $type = $definition['keys'][$key]['type'] ?? 'string';

        return match ($type) {
            'boolean' => in_array($value, ['1', 'on', 'true'], true),
            'integer' => $value === null || $value === '' ? null : (int) $value,
            'array', 'multi_select' => is_array($value) ? $value : $this->decode($value),
            'object' => is_array($value) ? $value : $this->decode($value),
            default => $value,
        };
    }

    /**
     * Normalize an incoming value before persistence.
     */
    protected function normalize(string $group, string $key, mixed $value): mixed
    {
        $definition = $this->definition($group);
        $type = $definition['keys'][$key]['type'] ?? 'string';

        return match ($type) {
            'boolean' => $this->toBooleanFlag($value),
            'integer' => $value === null || $value === '' ? null : (string) (int) $value,
            'array', 'multi_select', 'object' => is_array($value) ? $value : [$value],
            default => is_bool($value) ? ($value ? '1' : '0') : $value,
        };
    }

    private function toBooleanFlag(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return in_array($value, ['1', 'on', 'true', 'yes', 'y'], true) ? '1' : '0';
    }

    private function decode(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function definition(string $group): ?array
    {
        return config("business-settings.groups.{$group}", null);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function groups(): array
    {
        return config('business-settings.groups', []);
    }
}
