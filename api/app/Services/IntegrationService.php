<?php

namespace App\Services;

use App\Models\Integration;
use App\Support\IntegrationGroupDefinitions;

/**
 * Connector framework for third-party providers declared in
 * config/business-integrations.php. Credentials are stored in the matching
 * `business-settings` group (namespace `business`); connection state is tracked
 * on the `integrations` table. No provider is ever called out to: `connect`
 * validates that every credential is present and `test` reports the stored
 * state, so the surface is safe to use without live keys.
 */
class IntegrationService
{
    public function __construct(
        protected BusinessSettingsService $settings
    ) {}

    /**
     * Provider catalog with per-company connection state.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(?int $companyId): array
    {
        $catalog = [];

        foreach ($this->groups() as $provider => $group) {
            $catalog[] = $this->summary($companyId, (string) $provider, $group);
        }

        return $catalog;
    }

    /**
     * Category key => label pairs for catalog grouping.
     *
     * @return array<string, string>
     */
    public function categories(): array
    {
        return config('business-integrations.categories', []);
    }

    public function exists(string $provider): bool
    {
        return array_key_exists($provider, $this->groups());
    }

    /**
     * Full provider detail, secrets masked, with connection state.
     *
     * @return array<string, mixed>|null
     */
    public function show(?int $companyId, string $provider): ?array
    {
        $group = $this->groups()[$provider] ?? null;

        if ($group === null) {
            return null;
        }

        $values = $this->settings->group($companyId, $provider);
        $required = $this->requiredFields($provider);
        $enabled = (bool) ($values['enabled'] ?? false);

        $fields = [];

        foreach ($group['keys'] as $name => $definition) {
            if ($name === 'enabled') {
                continue;
            }

            $fields[] = $this->fieldView((string) $name, $definition, $values[$name] ?? null, in_array($name, $required, true));
        }

        $missing = array_values(array_filter(
            $required,
            fn (string $name) => ! $this->filled($values[$name] ?? null)
        ));

        $row = $this->connectionFor($companyId, $provider);

        return [
            'provider' => $provider,
            'label' => $group['label'],
            'description' => $group['description'],
            'category' => $this->categoryFor($provider),
            'category_label' => $this->categoryLabel($provider),
            'status' => $this->statusFor($enabled, $missing === [], $row),
            'is_connected' => $this->isConnected($enabled, $missing === [], $row),
            'enabled' => $enabled,
            'configured' => $missing === [],
            'required_fields' => $required,
            'missing_fields' => $missing,
            'connected_at' => $row?->connected_at?->toIso8601String(),
            'last_tested_at' => $row?->last_tested_at?->toIso8601String(),
            'last_test_status' => $row?->last_test_status,
            'fields' => $fields,
        ];
    }

    /**
     * Persist credentials (and optionally the enabled flag), then recompute the
     * connection state. Blank secret values are ignored so an existing secret is
     * never accidentally cleared by an update that omits it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function update(?int $companyId, string $provider, array $data): ?array
    {
        $group = $this->groups()[$provider] ?? null;

        if ($group === null) {
            return null;
        }

        $payload = [];

        foreach ($group['keys'] as $name => $definition) {
            if (! array_key_exists($name, $data)) {
                continue;
            }

            if ($definition['type'] === 'secret' && ! $this->filled($data[$name])) {
                continue;
            }

            $payload[$name] = $data[$name];
        }

        if ($payload !== []) {
            $this->settings->set($companyId, $provider, $payload);
        }

        $override = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : null;

        $this->sync($companyId, $provider, $override);

        return $this->show($companyId, $provider);
    }

    /**
     * Enable a provider after validating its credentials are complete.
     *
     * @return array<string, mixed>|null
     */
    public function connect(?int $companyId, string $provider): ?array
    {
        if (! $this->exists($provider)) {
            return null;
        }

        $this->settings->set($companyId, $provider, ['enabled' => true]);
        $this->sync($companyId, $provider, true);

        return $this->show($companyId, $provider);
    }

    /**
     * Disconnect a provider, keeping its stored credentials.
     *
     * @return array<string, mixed>|null
     */
    public function disconnect(?int $companyId, string $provider): ?array
    {
        if (! $this->exists($provider)) {
            return null;
        }

        $this->settings->set($companyId, $provider, ['enabled' => false]);
        $this->sync($companyId, $provider, false);

        return $this->show($companyId, $provider);
    }

    /**
     * Record a connection test. The framework never performs an outbound call;
     * a connected provider is reported as a successful test.
     *
     * @return array<string, mixed>|null
     */
    public function test(?int $companyId, string $provider): ?array
    {
        if (! $this->exists($provider)) {
            return null;
        }

        $row = $this->connectionFor($companyId, $provider);

        if ($row === null || ! $row->isConnected()) {
            return null;
        }

        $row->forceFill([
            'last_tested_at' => now(),
            'last_test_status' => Integration::TEST_SUCCESS,
        ])->save();

        return $this->show($companyId, $provider);
    }

    /**
     * Credential field names that must be populated before a provider can be
     * considered configured. Every secret field is required; providers without
     * secret fields require all of their fields.
     *
     * @return list<string>
     */
    public function requiredFields(string $provider): array
    {
        $group = $this->groups()[$provider] ?? null;

        if ($group === null) {
            return [];
        }

        $secrets = [];
        $all = [];

        foreach ($group['keys'] as $name => $definition) {
            if ($name === 'enabled') {
                continue;
            }

            $all[] = (string) $name;

            if ($definition['type'] === 'secret') {
                $secrets[] = (string) $name;
            }
        }

        return $secrets === [] ? $all : $secrets;
    }

    /**
     * Recompute and persist the stored connection state for a provider.
     */
    private function sync(?int $companyId, string $provider, ?bool $override = null): void
    {
        $values = $this->settings->group($companyId, $provider);
        $enabled = $override ?? (bool) ($values['enabled'] ?? false);

        $missing = array_values(array_filter(
            $this->requiredFields($provider),
            fn (string $name) => ! $this->filled($values[$name] ?? null)
        ));

        $configured = $missing === [];
        $row = $this->connectionFor($companyId, $provider);

        $status = $this->statusFor($enabled, $configured, $row, $override);

        $attributes = [
            'company_id' => $companyId,
            'provider' => $provider,
            'status' => $status,
            'is_connected' => $status === Integration::STATUS_CONNECTED,
        ];

        if ($row === null) {
            $attributes['connected_at'] = $status === Integration::STATUS_CONNECTED ? now() : null;
            $attributes['created_by'] = auth('api')->id();
            Integration::create($attributes);

            return;
        }

        $attributes['connected_at'] = $status === Integration::STATUS_CONNECTED
            ? ($row->connected_at ?? now())
            : null;

        $row->fill($attributes)->save();
    }

    /**
     * Resolve the connection status. An explicit override (the enabled flag on
     * a write) wins; otherwise a provider is disabled when it was explicitly
     * disconnected, connected when enabled and fully configured, and
     * configuration_required otherwise.
     */
    private function statusFor(bool $enabled, bool $configured, ?Integration $row, ?bool $override = null): string
    {
        if ($override === false) {
            return Integration::STATUS_DISABLED;
        }

        if ($override === true) {
            return $configured ? Integration::STATUS_CONNECTED : Integration::STATUS_CONFIGURATION_REQUIRED;
        }

        if (! $enabled) {
            return $row?->status === Integration::STATUS_DISABLED
                ? Integration::STATUS_DISABLED
                : Integration::STATUS_CONFIGURATION_REQUIRED;
        }

        return $configured ? Integration::STATUS_CONNECTED : Integration::STATUS_CONFIGURATION_REQUIRED;
    }

    private function isConnected(bool $enabled, bool $configured, ?Integration $row): bool
    {
        return $this->statusFor($enabled, $configured, $row) === Integration::STATUS_CONNECTED;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function fieldView(string $name, array $definition, mixed $value, bool $required): array
    {
        $type = (string) ($definition['type'] ?? 'string');
        $filled = $this->filled($value);

        $view = [
            'name' => $name,
            'label' => $definition['label'] ?? $name,
            'type' => $type,
            'required' => $required,
            'filled' => $filled,
            'value' => $type === 'secret'
                ? ($filled ? '********' : '')
                : ($value ?? ''),
        ];

        if (isset($definition['options'])) {
            $view['options'] = $definition['options'];
        }

        return $view;
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function summary(?int $companyId, string $provider, array $group): array
    {
        $values = $this->settings->group($companyId, $provider);
        $enabled = (bool) ($values['enabled'] ?? false);

        $missing = array_values(array_filter(
            $this->requiredFields($provider),
            fn (string $name) => ! $this->filled($values[$name] ?? null)
        ));

        $row = $this->connectionFor($companyId, $provider);

        return [
            'provider' => $provider,
            'label' => $group['label'],
            'description' => $group['description'],
            'category' => $this->categoryFor($provider),
            'category_label' => $this->categoryLabel($provider),
            'status' => $this->statusFor($enabled, $missing === [], $row),
            'is_connected' => $this->isConnected($enabled, $missing === [], $row),
            'enabled' => $enabled,
            'configured' => $missing === [],
            'missing_fields' => $missing,
            'connected_at' => $row?->connected_at?->toIso8601String(),
            'last_tested_at' => $row?->last_tested_at?->toIso8601String(),
            'last_test_status' => $row?->last_test_status,
        ];
    }

    private function connectionFor(?int $companyId, string $provider): ?Integration
    {
        if ($companyId === null) {
            return null;
        }

        return Integration::query()
            ->withoutGlobalScope('company_scope')
            ->where('company_id', $companyId)
            ->where('provider', $provider)
            ->first();
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    private function categoryFor(string $provider): string
    {
        return (string) (config("business-integrations.providers.{$provider}.category", ''));
    }

    private function categoryLabel(string $provider): string
    {
        $category = $this->categoryFor($provider);

        return $this->categories()[$category] ?? $category;
    }

    /**
     * Typed settings groups generated from the integration catalog.
     *
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     keys: array<string, array{label: string, type: string, default: mixed, options?: array<int, string>}>
     * }>
     */
    private function groups(): array
    {
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

        return IntegrationGroupDefinitions::groups($catalog);
    }
}
