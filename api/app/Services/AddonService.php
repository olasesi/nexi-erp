<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Resolves the add-on catalog (config/addons.php) against the per-company
 * toggle state stored in the `addons` business settings group.
 *
 * Toggles are layered the same way every other setting is: a global row
 * overrides the catalog default, a company row overrides both.
 */
class AddonService
{
    public const NAMESPACE = 'business';

    public const GROUP = 'addons';

    /**
     * The catalog as declared in config/addons.php.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        return config('addons', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $id): ?array
    {
        return $this->catalog()[$id] ?? null;
    }

    /**
     * Whether the add-on is switched on for the given company.
     */
    public function enabled(string $id, ?int $companyId = null): bool
    {
        $definition = $this->definition($id);

        if ($definition === null) {
            return false;
        }

        $value = $this->value($companyId, $id);

        if ($value === null) {
            return (bool) ($definition['enabled_by_default'] ?? false);
        }

        return in_array($value, ['1', 'on', 'true'], true);
    }

    /**
     * Switch an add-on on or off for the given company.
     */
    public function set(string $id, bool $enabled, ?int $companyId = null): void
    {
        if ($this->definition($id) === null) {
            return;
        }

        Setting::persist(self::NAMESPACE, $companyId, self::GROUP, $id, $enabled ? '1' : '0');
    }

    /**
     * The catalog merged with each add-on's effective toggle state, ready for
     * GET /api/v1/addons.
     *
     * @return array<int, array<string, mixed>>
     */
    public function status(?int $companyId = null): array
    {
        return array_map(function (array $addon, string $id) use ($companyId): array {
            return [
                'id' => $id,
                'label' => $addon['label'],
                'description' => $addon['description'] ?? null,
                'category' => $addon['category'] ?? null,
                'status' => $addon['status'] ?? 'free',
                'installed' => true,
                'enabled' => $this->enabled($id, $companyId),
                'enabled_by_default' => (bool) ($addon['enabled_by_default'] ?? false),
            ];
        }, $this->catalog(), array_keys($this->catalog()));
    }

    /**
     * Effective toggle value: company row wins over global row, which wins
     * over nothing (caller falls back to the catalog default).
     */
    private function value(?int $companyId, string $id): ?string
    {
        $base = $this->row(null, $id);

        if ($companyId === null) {
            return $base;
        }

        return $this->row($companyId, $id) ?? $base;
    }

    private function row(?int $companyId, string $id): ?string
    {
        return Setting::query()
            ->where('namespace', self::NAMESPACE)
            ->where('group', self::GROUP)
            ->where('key', $id)
            ->when(
                $companyId === null,
                fn ($query) => $query->whereNull('company_id'),
                fn ($query) => $query->where('company_id', $companyId)
            )
            ->value('value');
    }
}
