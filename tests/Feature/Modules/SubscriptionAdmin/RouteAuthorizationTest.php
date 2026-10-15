<?php

use App\Models\Plan;
use Database\Factories\PlanFactory;
use Illuminate\Support\Facades\Route;
use Tests\Support\Actors;

/*
| SADM-01: subscription and plan administration is platform-only. It moved from the old
| admin.subscriptions.* / admin.plans.* routes to /platform (WP8a), behind auth:platform.
| A tenant (company owner) and a plain user are sent to the platform login and change nothing.
| The screens themselves are covered by Platform/PlatformAreaTest ("renders every platform screen").
*/

dataset('subscription admin routes', function () {
    return [
        'subscriptions.dashboard' => ['get', fn ($ctx) => route('platform.subscriptions.dashboard')],
        'subscriptions.index' => ['get', fn ($ctx) => route('platform.subscriptions.index')],
        'subscriptions.payments' => ['get', fn ($ctx) => route('platform.subscriptions.payments')],
        'subscriptions.grant' => ['post', fn ($ctx) => route('platform.companies.subscription.grant', $ctx['company'])],
        'subscriptions.expire' => ['post', fn ($ctx) => route('platform.companies.subscription.expire', $ctx['company'])],
        'subscriptions.suspend' => ['post', fn ($ctx) => route('platform.companies.subscription.suspend', $ctx['company'])],
        'subscriptions.reactivate' => ['post', fn ($ctx) => route('platform.companies.subscription.reactivate', $ctx['company'])],
        'plans.index' => ['get', fn ($ctx) => route('platform.plans.index')],
        'plans.create' => ['get', fn ($ctx) => route('platform.plans.create')],
        'plans.store' => ['post', fn ($ctx) => route('platform.plans.store')],
        'plans.edit' => ['get', fn ($ctx) => route('platform.plans.edit', $ctx['plan'])],
        'plans.update' => ['put', fn ($ctx) => route('platform.plans.update', $ctx['plan'])],
        'plans.destroy' => ['delete', fn ($ctx) => route('platform.plans.destroy', $ctx['plan'])],
    ];
});

dataset('tenant actors', [
    'tenant' => [fn () => Actors::companyOwner()],
    'plain user' => [fn () => Actors::plainUser()],
]);

function subscriptionAdminContext(): array
{
    $company = Actors::company();

    return [
        'company' => $company,
        'subscription' => Actors::openSubscription($company),
        'plan' => PlanFactory::new()->create(['is_active' => true]),
    ];
}

it('sends tenant users to the platform login and changes nothing', function (Closure $actor, string $method, Closure $url) {
    $ctx = subscriptionAdminContext();
    $plan = $ctx['plan'];
    $before = $ctx['subscription']->fresh()->toArray();

    $payload = ['name' => 'Hacked', 'slug' => 'hacked', 'days' => 3650];

    $this->actingAs($actor())->{$method}($url($ctx), $payload)->assertRedirect(route('platform.login'));

    expect(Plan::query()->find($plan->id))->not->toBeNull()
        ->and($plan->fresh()->name)->toBe($plan->name)
        ->and($plan->fresh()->is_active)->toBeTrue()
        ->and($ctx['subscription']->fresh()->toArray())->toEqual($before)
        ->and($ctx['company']->subscriptions()->count())->toBe(1);
})->with('tenant actors')->with('subscription admin routes');

it('sends guests to the platform login', function (string $method, Closure $url) {
    $ctx = subscriptionAdminContext();

    $this->{$method}($url($ctx))->assertRedirect(route('platform.login'));
})->with('subscription admin routes');

it('no longer has the tenant-side admin routes', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.subscriptions.dashboard', 'admin.subscriptions.index', 'admin.subscriptions.payments',
    'admin.subscriptions.show', 'admin.subscriptions.grant', 'admin.plans.index', 'admin.plans.store',
]);
