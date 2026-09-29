<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Read-only view over config('api.versions'). It answers which versions the
 * API publishes, how they are negotiated and when one of them stops being
 * served, so the middleware, the discovery endpoint and the tests all agree.
 */
final class ApiVersions
{
    public const STATUS_STABLE = 'stable';

    public const STATUS_DEPRECATED = 'deprecated';

    public const STATUS_SUNSET = 'sunset';

    /**
     * Raw configuration keyed by version.
     *
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $versions = config('api.versions', []);

        return is_array($versions) ? $versions : [];
    }

    public static function default(): string
    {
        return strtolower((string) config('api.default', 'v1'));
    }

    public static function header(): string
    {
        return (string) config('api.header', 'X-Api-Version');
    }

    public static function pathPrefix(): string
    {
        return (string) config('api.path_prefix', 'api');
    }

    /**
     * Every published version, lowest first.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        $versions = array_keys(self::config());
        sort($versions);

        return array_map('strtolower', $versions);
    }

    public static function supports(?string $version): bool
    {
        return $version !== null && in_array(strtolower($version), self::supported(), true);
    }

    public static function mediaType(string $version): string
    {
        return self::mediaPrefix().'.'.strtolower($version).'+json';
    }

    /**
     * Sunset moment of a version, or null when it has no scheduled end.
     */
    public static function sunsetAt(string $version): ?Carbon
    {
        $entry = self::config()[strtolower($version)] ?? null;
        $at = is_array($entry) ? ($entry['sunset_at'] ?? null) : null;

        return is_string($at) && $at !== '' ? Carbon::parse($at)->startOfDay() : null;
    }

    /**
     * Machine readable description of a single version.
     *
     * @return array<string, mixed>
     */
    public static function describe(string $version): array
    {
        $version = strtolower($version);
        $entry = self::config()[$version] ?? [];
        $entry = is_array($entry) ? $entry : [];
        $sunset = self::sunsetAt($version);
        $status = (string) ($entry['status'] ?? self::STATUS_STABLE);

        if ($sunset !== null && $sunset->isPast()) {
            $status = self::STATUS_SUNSET;
        }

        return [
            'version' => $version,
            'status' => $status,
            'media_type' => self::mediaType($version),
            'sunset_at' => $sunset?->toIso8601String(),
            'replaced_by' => $entry['replaced_by'] ?? null,
            'notice' => $entry['notice'] ?? null,
        ];
    }

    public static function isDeprecated(string $version): bool
    {
        return in_array(self::describe($version)['status'], [self::STATUS_DEPRECATED, self::STATUS_SUNSET], true);
    }

    /**
     * True once the version must no longer be served.
     */
    public static function isSunset(string $version): bool
    {
        return self::describe($version)['status'] === self::STATUS_SUNSET;
    }

    /**
     * Media type prefix, e.g. application/vnd.nexi.
     */
    public static function mediaPrefix(): string
    {
        return (string) config('api.media_type', 'application/vnd.nexi');
    }
}
