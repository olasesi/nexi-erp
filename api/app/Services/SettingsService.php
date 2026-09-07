<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class SettingsService
{
    /**
     * Resolve the effective scope for the given company id.
     */
    public function companyScope(?int $companyId): ?int
    {
        return $companyId;
    }

    /**
     * All settings as grouped array, defaults overridden by global rows,
     * then by company-specific rows.
     */
    public function values(?int $companyId = null): array
    {
        $groups = config('settings.groups');
        $values = [];

        foreach ($groups as $group => $definition) {
            $values[$group] = $this->group($companyId, $group);
        }

        return $values;
    }

    /**
     * Single settings group, defaults overridden by stored values (global rows
     * first, then company-specific rows).
     */
    public function group(?int $companyId, string $group): array
    {
        $definition = config("settings.groups.{$group}", null);

        if ($definition === null) {
            return [];
        }

        $values = $definition['defaults'] ?? [];

        foreach (Setting::where('group', $group)->whereNull('company_id')->get() as $row) {
            $values[$row->key] = $row->decryptedValue();
        }

        if ($companyId !== null) {
            foreach (Setting::where('company_id', $companyId)->where('group', $group)->get() as $row) {
                $values[$row->key] = $row->decryptedValue();
            }
        }

        return $values;
    }

    /**
     * Upsert a batch of keys for a group in the given scope.
     */
    public function set(?int $companyId, string $group, array $values): void
    {
        $definition = config("settings.groups.{$group}", null);

        if ($definition === null) {
            return;
        }

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $definition['keys'])) {
                continue;
            }

            Setting::persist(
                $companyId,
                $group,
                $key,
                is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : $value)
            );
        }
    }

    /**
     * Approximate cache footprint in megabytes.
     */
    public function cacheSize(): string
    {
        $bytes = $this->directorySize(storage_path('framework/cache'));

        return number_format($bytes / 1048576, 2);
    }

    /**
     * Flush application-level caches.
     */
    public function clearCache(): void
    {
        foreach (['config:clear', 'cache:clear', 'view:clear', 'route:clear'] as $command) {
            Artisan::call($command);
        }
    }

    /**
     * Send a test email using the configured mail transport.
     */
    public function sendTestEmail(?int $companyId, string $address): void
    {
        $settings = $this->group($companyId, 'email');
        $driver = $settings['mailDriver'] ?: config('mail.default');
        $host = $settings['mailHost'] ?: config('mail.mailers.smtp.host');
        $port = $settings['mailPort'] ?: config('mail.mailers.smtp.port');
        $encryption = $settings['mailEncryption'] ?: config('mail.mailers.smtp.encryption');
        $username = $settings['mailUsername'] ?: config('mail.mailers.smtp.username');
        $password = $settings['mailPassword'] ?: config('mail.mailers.smtp.password');
        $fromAddress = $settings['mailFromAddress'] ?: config('mail.from.address');
        $fromName = $settings['mailFromName'] ?: config('mail.from.name');

        config([
            'mail.default' => $driver,
            'mail.mailers.smtp.host' => $host ?: 'localhost',
            'mail.mailers.smtp.port' => $port ?: 587,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $fromAddress ?: 'noreply@'.preg_replace('/^www\./', '', request()->getHost()),
            'mail.from.name' => $fromName,
        ]);

        Mail::raw('This is a test email sent from Nexi ERP. If you can read this, your mail settings work.', function ($message) use ($address) {
            $message->to($address)->subject('Nexi ERP: Test Email');
        });
    }

    private function directorySize(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $size = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
