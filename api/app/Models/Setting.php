<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Setting extends Model
{
    protected $fillable = [
        'company_id',
        'namespace',
        'group',
        'key',
        'value',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Persist a value, encrypting it when the key is a secret in its namespace.
     */
    public static function persist(
        ?string $namespace,
        ?int $companyId,
        string $group,
        string $key,
        ?string $value
    ): void {
        $namespace = $namespace ?: 'app';
        $secret = self::isSecretKey($namespace, $group, $key);

        self::updateOrCreate(
            ['company_id' => $companyId, 'namespace' => $namespace, 'group' => $group, 'key' => $key],
            ['value' => $value === null || $value === '' ? null : ($secret ? Crypt::encryptString($value) : $value)]
        );
    }

    /**
     * Decrypt a stored value when the key is a secret in its namespace.
     */
    public function decryptedValue(): ?string
    {
        $secret = self::isSecretKey((string) $this->namespace, $this->group, $this->key);

        if ($this->value === null || $this->value === '') {
            return null;
        }

        if ($secret && Str::startsWith($this->value, 'eyJ')) {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Throwable) {
                return $this->value;
            }
        }

        return $this->value;
    }

    /**
     * Decide whether a value must be encrypted at rest. The business namespace
     * derives secrets from the typed key definitions; the app namespace uses the
     * explicit settings.secrets allow-list.
     */
    public static function isSecretKey(string $namespace, string $group, string $key): bool
    {
        if ($namespace === 'business') {
            $definition = config("business-settings.groups.{$group}.keys.{$key}", null);

            return isset($definition['type']) && $definition['type'] === 'secret';
        }

        return in_array("{$group}.{$key}", config('settings.secrets', []), true);
    }
}
