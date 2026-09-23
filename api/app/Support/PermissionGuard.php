<?php

namespace App\Support;

class PermissionGuard
{
    /**
     * The guard roles and permissions are stored under.
     *
     * During any authenticated API request the auth middleware switches the
     * default guard to the Passport guard ("api"), so Spatie resolves every
     * permission lookup against that guard - roles/permissions must be written
     * under it too or checks never match.
     */
    public static function name(): string
    {
        foreach (config('auth.guards') as $name => $guard) {
            if ($guard['driver'] === 'passport') {
                return $name;
            }
        }

        return config('auth.defaults.guard');
    }
}
