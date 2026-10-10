<?php

use Database\Factories\PlanFactory;
use Tests\Support\Actors;

/*
| SADM-01: every admin.subscriptions.* and admin.plans.* route is super-admin only.
| A tenant (company owner) and a plain user must get 403 and change nothing.
*/

dataset('subscription admin routes', function () {
    return [
        'subscriptions.dashboard' => ['get', fn ($ctx) => route('admin.subscriptions.dashboard')],
        'subscriptions.index' => ['get', fn ($ctx) => route('admin.subscriptions.index')],
        'subscriptions.payments' => ['get', fn ($ctx) => route('admin.subscriptions.payments')],
        'subscriptions.show' => ['get', fn ($ctx) => route('admin.subscriptions.show', $ctx['victim'])],
        'subscriptions.grant' => ['post', fn ($ctx) => route('admin.subscriptions.grant', $ctx['victim'])],
        'subscriptions.expire' => ['post', fn ($ctx) => route('admin.subscriptions.expire', $ctx['subscription'])],
        'subscriptions.suspend' => ['post', fn ($ctx) => route('admin.subscriptions.suspend', $ctx['subscription'])],
        'subscriptions.reactivate' => ['post', fn ($ctx) => route('admin.subscriptions.reactivate', $ctx['subscription'])],
        'plans.index' => ['get', fn ($ctx) => route('admin.plans.index')],
        'plans.create' => ['get', fn ($ctx) => route('admin.plans.create')],
        'plans.store' => ['post', fn ($ctx) => route('admin.plans.store')],
        'plans.edit' => ['get', fn ($ctx) => route('admin.plans.edit', $ctx['plan'])],
        'plans.update' => ['put', fn ($ctx) => route('admin.plans.update', $ctx['plan'])],
        'plans.destroy' => ['delete', fn ($ctx) => route('admin.plans.destroy', $ctx['plan'])],
        'plans.toggle' => ['post', fn ($ctx) => route('admin.plans.toggle', $ctx['plan'])],
    ];
});

dataset('non super-admin actors', [
    'tenant' => [fn () => Actors::companyOwner()],
    'plain user' => [fn () => Actors::plainUser()],
]);

function subscriptionAdminContext(): array
{
    $victim = Actors::companyOwner();

    return [
        'victim' => $victim,
        'subscription' => $victim->subscriptions()->firstOrFail(),
        'plan' => PlanFactory::new()->create(['is_active' => true]),
    ];
}

it('returns 403 for non super-admins', function (Closure $actor, string $method, Closure $url) {
    $ctx = subscriptionAdminContext();
    $plan = $ctx['plan'];
    $subscription = $ctx['subscription'];
    $before = $subscription->fresh()->toArray();

    $payload = [
        'plan_id' => $plan->id,
        'name' => 'Hacked',
        'slug' => 'hacked',
        'price' => 0,
        'currency' => 'USD',
        'billing_cycle' => 'monthly',
        'duration_days' => 3650,
    ];

    $this->actingAs($actor())->{$method}($url($ctx), $payload)->assertForbidden();

    expect($plan->fresh())->not->toBeNull()
        ->and($plan->fresh()->name)->toBe($plan->name)
        ->and($plan->fresh()->is_active)->toBeTrue()
        ->and($subscription->fresh()->toArray())->toEqual($before)
        ->and($ctx['victim']->subscriptions()->count())->toBe(1);
})->with('non super-admin actors')->with('subscription admin routes');

it('redirects guests to login', function (string $method, Closure $url) {
    $ctx = subscriptionAdminContext();

    $this->{$method}($url($ctx))->assertRedirect(route('login'));
})->with('subscription admin routes');

it('lets a super-admin open the read-only pages', function (string $routeName) {
    $ctx = subscriptionAdminContext();
    $params = match ($routeName) {
        'admin.subscriptions.show' => [$ctx['victim']],
        'admin.plans.edit' => [$ctx['plan']],
        default => [],
    };

    $this->actingAs(Actors::superAdmin())->get(route($routeName, $params))->assertOk();
})->with([
    'admin.subscriptions.dashboard',
    'admin.subscriptions.index',
    'admin.subscriptions.payments',
    'admin.subscriptions.show',
    'admin.plans.index',
    'admin.plans.create',
    'admin.plans.edit',
]);
