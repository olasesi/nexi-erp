<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Throttle the API per authenticated user (or client IP for guests).
        // Tests raise the limit through the API_RATE_LIMIT env in phpunit.xml
        // so suite-wide fixtures do not trip the limiter.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('api.rate_limit', 120))
                ->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });
    }
}
