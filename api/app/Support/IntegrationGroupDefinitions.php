<?php

namespace App\Support;

class IntegrationGroupDefinitions
{
    /**
     * Build typed settings groups from the integration catalog.
     *
     * @param  array{
     *     categories: array<string, string>,
     *     providers: array<string, array{
     *         category: string,
     *         label: string,
     *         description?: string,
     *         fields: array<string, string|array{label?: string, type: string, default?: mixed, options?: array<int, string>}>
     *     }>
     * }  $catalog
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     keys: array<string, array{label: string, type: string, default: mixed, options?: array<int, string>}>
     * }>
     */
    public static function groups(array $catalog): array
    {
        $groups = [];

        foreach ($catalog['providers'] as $provider => $definition) {
            $categoryLabel = $catalog['categories'][$definition['category']] ?? $definition['category'];

            $keys = [
                'enabled' => ['label' => 'Enabled', 'type' => 'boolean', 'default' => '0'],
            ];

            foreach ($definition['fields'] as $name => $field) {
                $keys[$name] = self::field($name, $field);
            }

            $groups[$provider] = [
                'label' => $definition['label'],
                'description' => $definition['description'] ?? null
                    ? "{$categoryLabel} - {$definition['description']}"
                    : "{$categoryLabel} configuration for {$definition['label']}.",
                'keys' => $keys,
            ];
        }

        return $groups;
    }

    /**
     * Normalize a single catalog field into a typed key definition.
     *
     * @param  string|array{label?: string, type: string, default?: mixed, options?: array<int, string>}  $field
     * @return array{label: string, type: string, default: mixed, options?: array<int, string>}
     */
    private static function field(string $name, string|array $field): array
    {
        if (is_string($field)) {
            return [
                'label' => ucwords(str_replace('_', ' ', $name)),
                'type' => $field,
                'default' => self::defaultFor($field, []),
            ];
        }

        $type = $field['type'];

        $definition = [
            'label' => $field['label'] ?? ucwords(str_replace('_', ' ', $name)),
            'type' => $type,
            'default' => $field['default'] ?? self::defaultFor($type, $field['options'] ?? []),
        ];

        if ($type === 'select') {
            $definition['options'] = $field['options'] ?? [];
        }

        return $definition;
    }

    /**
     * Sensible defaults so select/boolean/secret values are never null.
     *
     * @param  array<int, string>  $options
     */
    private static function defaultFor(string $type, array $options): mixed
    {
        return match ($type) {
            'boolean' => '0',
            'select' => $options[0] ?? '',
            'integer' => null,
            default => '',
        };
    }
}
