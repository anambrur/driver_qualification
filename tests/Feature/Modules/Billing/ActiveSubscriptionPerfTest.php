<?php

use App\Models\Subscription;
use App\Services\Stripe\StripeClientFactory;
use Database\Factories\SubscriptionFactory;
use Illuminate\Support\Facades\Event;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| BIL-05: User::activeSubscription() runs on every request behind the Subscribed gate, so it
| must not hydrate the user's whole subscription history. StripeClientFactory must not keep
| a client built from a stale secret.
*/

it('hydrates only accessible subscriptions when resolving the active one', function () {
    $user = Actors::companyOwner(subscribed: false);
    SubscriptionFactory::new()->expired()->count(25)->create(['user_id' => $user->id, 'created_at' => now()->subYear()]);
    $active = Actors::subscribe($user);

    $hydrated = 0;
    Event::listen('eloquent.retrieved: '.Subscription::class, function () use (&$hydrated) {
        $hydrated++;
    });

    $queries = Perf::countQueries(fn () => expect($user->activeSubscription()?->id)->toBe($active->id));

    expect($hydrated)->toBe(1)
        ->and($queries)->toBe(1);
});

it('still returns null when nothing is accessible', function () {
    $user = Actors::companyOwner(subscribed: false);
    SubscriptionFactory::new()->expired()->count(3)->create(['user_id' => $user->id]);

    expect($user->activeSubscription())->toBeNull()
        ->and($user->hasActiveSubscription())->toBeFalse();
});

it('keeps the Subscribed gate working', function () {
    $this->actingAs(Actors::companyOwner())->get(route('admin.driver.index'))->assertOk();
    $this->actingAs(Actors::expiredCompanyOwner())->get(route('admin.driver.index'))->assertRedirect(route('pricing.plans'));
});

it('builds a new Stripe client when the secret changes', function () {
    config(['services.stripe.secret' => 'sk_test_first']);
    $first = (new StripeClientFactory)->make();

    config(['services.stripe.secret' => 'sk_test_second']);
    $second = (new StripeClientFactory)->make();

    expect($first->getApiKey())->toBe('sk_test_first')
        ->and($second->getApiKey())->toBe('sk_test_second')
        ->and((new StripeClientFactory)->make())->toBe($second);
});
