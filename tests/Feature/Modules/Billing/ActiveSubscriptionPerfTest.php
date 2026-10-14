<?php

use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Services\Billing\AccessState;
use App\Services\Billing\AccessStateResolver;
use App\Services\Stripe\StripeClientFactory;
use Database\Factories\SubscriptionFactory;
use Illuminate\Support\Facades\Event;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| BIL-05: the access state runs on every request behind `subscribed`, so it must not hydrate the
| company's subscription history. StripeClientFactory must not keep a client built from a
| stale secret.
*/

it('hydrates only the open subscription when resolving the access state', function () {
    $company = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($company)->expired()->count(25)->create(['created_at' => now()->subYear()]);
    SubscriptionFactory::new()->forCompany($company)->create();
    SiteSetting::getSettings();
    app(AccessStateResolver::class)->forget($company->id);

    $hydrated = 0;
    Event::listen('eloquent.retrieved: '.Subscription::class, function () use (&$hydrated) {
        $hydrated++;
    });

    $queries = Perf::countQueries(fn () => expect(AccessState::for($company))->toBe(AccessState::Full));

    expect($hydrated)->toBe(1)
        ->and($queries)->toBe(1);
});

it('is read-only, not none, when every subscription has ended', function () {
    $company = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($company)->expired()->count(3)->create();

    expect(AccessState::for($company))->toBe(AccessState::ReadOnly)
        ->and(AccessStateResolver::accessibleSubscription($company))->toBeNull();
});

it('keeps the subscribed gate working', function () {
    $this->actingAs(Actors::companyOwner())->get(route('profile.edit'))->assertOk();
    $this->actingAs(Actors::company(subscribed: false)->owner)->get(route('profile.edit'))->assertRedirect(route('pricing.plans'));
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
