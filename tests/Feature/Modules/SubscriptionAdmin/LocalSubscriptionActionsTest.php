<?php

use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionNotificationService;
use App\Services\Stripe\StripeClientFactory;
use Database\Factories\PlanFactory;
use Database\Factories\SubscriptionFactory;
use Tests\Support\Actors;
use Tests\Support\StripeFake;

/*
| SADM-03: expire / suspend / reactivate on admin-granted or trial subscriptions (no Stripe id)
| must never reach Stripe and must leave a sane, time-bounded state. `grant` must refuse while
| the user already has an accessible subscription.
*/

beforeEach(function () {
    $this->freezeSecond();

    $factory = Mockery::mock(StripeClientFactory::class);
    $factory->shouldNotReceive('make');
    app()->instance(StripeClientFactory::class, $factory);

    $this->notifications = Mockery::mock(SubscriptionNotificationService::class);
    $this->notifications->shouldReceive('sendActivated')->byDefault();
    app()->instance(SubscriptionNotificationService::class, $this->notifications);

    $this->admin = Actors::superAdmin();
    $this->tenant = Actors::companyOwner(subscribed: false);
});

function sadmGrant(User $tenant, array $overrides = []): Subscription
{
    $plan = PlanFactory::new()->create(['duration_days' => 30]);

    test()->actingAs(test()->admin)
        ->post(route('admin.subscriptions.grant', $tenant), array_merge(['plan_id' => $plan->id], $overrides))
        ->assertRedirect(route('admin.subscriptions.show', $tenant));

    return $tenant->subscriptions()->latest('id')->firstOrFail();
}

it('grants a time-bounded complimentary subscription', function () {
    $sub = sadmGrant($this->tenant);

    expect($sub->source)->toBe('admin')
        ->and($sub->stripe_subscription_id)->toBeNull()
        ->and($sub->isAccessible())->toBeTrue()
        ->and($sub->accessEndsAt()->equalTo(now()->addDays(30)))->toBeTrue();

    $this->travel(31)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('refuses to grant while the user has an accessible subscription', function (string $state) {
    $factory = SubscriptionFactory::new();
    $factory = match ($state) {
        'active stripe' => $factory,
        'trial' => $factory->trial(),
        'past_due' => $factory->pastDue(),
        'canceled, grace left' => $factory->state(['stripe_status' => 'canceled', 'ends_at' => now()->addDays(5)]),
    };
    $factory->create(['user_id' => $this->tenant->id]);
    $plan = PlanFactory::new()->create();

    $this->notifications->shouldNotReceive('sendActivated');

    $this->actingAs($this->admin)
        ->post(route('admin.subscriptions.grant', $this->tenant), ['plan_id' => $plan->id])
        ->assertRedirect(route('admin.subscriptions.show', $this->tenant));

    expect(json_encode(session()->all()))->toContain('Expire it first')
        ->and($this->tenant->subscriptions()->count())->toBe(1);
})->with(['active stripe', 'trial', 'past_due', 'canceled, grace left']);

it('allows a grant once the previous subscription has expired', function () {
    SubscriptionFactory::new()->expired()->create(['user_id' => $this->tenant->id]);

    expect(sadmGrant($this->tenant)->isAccessible())->toBeTrue()
        ->and($this->tenant->subscriptions()->count())->toBe(2);
});

it('expires an admin grant locally without calling Stripe', function () {
    $sub = sadmGrant($this->tenant);

    $this->actingAs($this->admin)->post(route('admin.subscriptions.expire', $sub))->assertRedirect();

    expect($sub->fresh()->stripe_status)->toBe('canceled')
        ->and($sub->fresh()->isAccessible())->toBeFalse()
        ->and($this->tenant->fresh()->hasActiveSubscription())->toBeFalse();
});

it('expires a trial locally without calling Stripe', function () {
    $sub = SubscriptionFactory::new()->trial()->create(['user_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->post(route('admin.subscriptions.expire', $sub))->assertRedirect();

    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('suspends an admin grant until its original end date', function () {
    $sub = sadmGrant($this->tenant);
    $endsAt = $sub->accessEndsAt();

    $this->actingAs($this->admin)->post(route('admin.subscriptions.suspend', $sub))->assertRedirect();

    $fresh = $sub->fresh();
    expect($fresh->onGracePeriod())->toBeTrue()
        ->and($fresh->accessEndsAt()->equalTo($endsAt))->toBeTrue();

    $this->travel(31)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('reactivating a suspended admin grant keeps its original end date', function () {
    $sub = sadmGrant($this->tenant);
    $endsAt = $sub->accessEndsAt();

    $this->actingAs($this->admin)->post(route('admin.subscriptions.suspend', $sub));
    $this->actingAs($this->admin)->post(route('admin.subscriptions.reactivate', $sub))->assertRedirect();

    $fresh = $sub->fresh();
    expect($fresh->stripe_status)->toBe('active')
        ->and($fresh->isAccessible())->toBeTrue()
        ->and($fresh->accessEndsAt()?->equalTo($endsAt))->toBeTrue();

    $this->travel(31)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('reactivating a suspended trial keeps the trial end date', function () {
    $sub = SubscriptionFactory::new()->trial(7)->create(['user_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->post(route('admin.subscriptions.suspend', $sub));
    $this->actingAs($this->admin)->post(route('admin.subscriptions.reactivate', $sub));

    expect($sub->fresh()->stripe_status)->toBe('trialing')
        ->and($sub->fresh()->isAccessible())->toBeTrue();

    $this->travel(8)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('refuses to reactivate an expired local subscription', function () {
    $sub = sadmGrant($this->tenant);
    $this->actingAs($this->admin)->post(route('admin.subscriptions.expire', $sub));

    $this->actingAs($this->admin)->post(route('admin.subscriptions.reactivate', $sub))->assertRedirect();

    expect($sub->fresh()->stripe_status)->toBe('canceled')
        ->and($sub->fresh()->isAccessible())->toBeFalse();
});

it('does not echo exception messages when reactivate fails', function () {
    $sub = SubscriptionFactory::new()->create([
        'user_id' => $this->tenant->id,
        'stripe_subscription_id' => 'sub_test_secret',
    ]);

    $client = StripeFake::bindClient();
    $client->subscriptions = Mockery::mock(\Stripe\Service\SubscriptionService::class);
    $client->subscriptions->shouldReceive('update')
        ->andThrow(new \Stripe\Exception\InvalidRequestException('No such subscription: sub_test_secret; request req_internal_123'));

    $this->actingAs($this->admin)->post(route('admin.subscriptions.reactivate', $sub))->assertRedirect();

    $flashed = json_encode(session()->all());
    expect($flashed)->not->toContain('req_internal_123')
        ->and($flashed)->not->toContain('No such subscription');
});

it('a tenant cannot turn an admin grant into lifetime access via billing cancel + resume', function () {
    $sub = sadmGrant($this->tenant);

    $this->actingAs($this->tenant)->post(route('billing.cancel'));
    $this->actingAs($this->tenant)->post(route('billing.resume'));

    expect($sub->fresh()->isAccessible())->toBeTrue();

    $this->travel(31)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});
