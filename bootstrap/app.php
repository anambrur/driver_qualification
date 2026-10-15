<?php

use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsurePlatformSession;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SeoRobotsHeader;
use App\Http\Middleware\Subscribed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stripe webhooks send POST without CSRF token - must exclude
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
        ]);
        // Global (outermost) so auth redirects and 404s also carry the noindex header
        $middleware->append(SeoRobotsHeader::class);
        $middleware->web(append: [
            EnsureUserIsActive::class,
            ResolveTenant::class,
        ]);
        // Route-model binding must run inside the tenant context (03 §4.1)
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenant::class,
        );
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,

            'subscribed' => Subscribed::class,
            'feature' => EnsureFeature::class,
            'platform' => EnsurePlatformSession::class,
        ]);
        // Each guard has its own login page and home (04 §2)
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform', 'platform/*')
            ? route('platform.login')
            : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('platform', 'platform/*')
            ? route('platform.dashboard')
            : route('dashboard'));
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
