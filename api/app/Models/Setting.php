<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Setting extends Model
{
    protected $fillable = [
        'company_id',
        'group',
        'key',
        'value',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Persist a value, encrypting it when the key is registered as secret.
     */
    public static function persist(?int $companyId, string $group, string $key, ?string $value): void
    {
        $secret = in_array("{$group}.{$key}", config('settings.secrets', []), true);

        self::updateOrCreate(
            ['company_id' => $companyId, 'group' => $group, 'key' => $key],
            ['value' => $value === null || $value === '' ? null : ($secret ? Crypt::encryptString($value) : $value)]
        );
    }

    /**
     * Decrypt a stored value when the key is registered as secret.
     */
    public function decryptedValue(): ?string
    {
        $secret = in_array("{$this->group}.{$this->key}", config('settings.secrets', []), true);

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
}
