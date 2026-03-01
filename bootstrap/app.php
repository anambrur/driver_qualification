<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\CheckApplicationSession;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stripe webhooks send POST without CSRF token - must exclude
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,

            'Subscribed'       => \App\Http\Middleware\Subscribed::class,
        ]);
    })

    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // ── Subscription Scheduler ────────────────────────────────────────────
        // Run daily at 1:00 AM to expire subscriptions and send warnings
        $schedule->command('subscriptions:check-expired')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/subscriptions.log'));
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
