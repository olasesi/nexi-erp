<?php

use App\Http\Middleware\ApiVersion;
use App\Http\Middleware\CollectMetrics;
use App\Http\Middleware\EnsureCustomerAccount;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsurePermitted;
use App\Http\Middleware\EnsureStaffAccount;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration as SentryIntegration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('webhooks:retry')->everyMinute()->withoutOverlapping();
        $schedule->command('webhooks:prune')->dailyAt('02:15')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CollectMetrics::class);

        $middleware->api(append: [ApiVersion::class]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'staff' => EnsureStaffAccount::class,
            'customer' => EnsureCustomerAccount::class,
            'permitted' => EnsurePermitted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        SentryIntegration::handles($exceptions);
    })->create();
