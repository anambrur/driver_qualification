<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\Actors;

/*
| ACL-03: every POST a guest can send must be rate limited, either by `throttle:` middleware or
| by a limiter the route is known to have. The OTP routes were throttled in APP-04; this covers
| the remaining guest forms (register, forgot-password, reset-password).
*/

// Public POST routes that are limited some other way.
const ACL_THROTTLE_EXEMPT = [
    // LoginRequest::ensureIsNotRateLimited(): 5 attempts per email + IP.
    'POST login',
    // Verified by the Stripe signature header; Stripe retries on failure.
    'POST stripe/webhook',
];

// Application steps and withdraw only work inside an OTP-verified application session (APP-01).
const ACL_THROTTLE_EXEMPT_PATTERN = '#^POST \{slug\}/application/(step-\d+|withdraw/\{driver_id\})$#';

it('rate limits every POST a guest can send', function () {
    $unthrottled = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('POST', $route->methods(), true))
        ->reject(fn (RoutingRoute $route) => in_array('auth', $route->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $route) => [
            'key' => 'POST '.$route->uri(),
            'throttled' => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')),
        ])
        ->reject(fn (array $route) => $route['throttled']
            || in_array($route['key'], ACL_THROTTLE_EXEMPT, true)
            || preg_match(ACL_THROTTLE_EXEMPT_PATTERN, $route['key']))
        ->pluck('key')->values()->all();

    expect($unthrottled)->toBe([]);
});

it('returns 429 after too many attempts from one client', function (string $uri) {
    Actors::seedAccessControl();

    for ($i = 0; $i < 6; $i++) {
        expect($this->post($uri, [])->status())->not->toBe(429);
    }

    $this->post($uri, [])->assertStatus(429);
})->with(['register', 'forgot-password', 'reset-password']);
