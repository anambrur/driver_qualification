<?php

use Database\Factories\SubscriptionFactory;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\SubscriptionService;
use Tests\Support\Actors;
use Tests\Support\StripeFake;

/*
| Convention: never return exception messages to the client. Stripe API errors carry
| internal ids and request details.
*/

it('does not show the Stripe error message when resuming fails', function () {
    Log::spy();
    $company = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($company)->cancelingAtPeriodEnd()->create();

    $client = StripeFake::bindClient();
    $client->subscriptions = Mockery::mock(SubscriptionService::class);
    $client->subscriptions->shouldReceive('update')
        ->andThrow(InvalidRequestException::factory('No such subscription: sub_internal_secret; request req_abc123'));

    $this->actingAs($company->owner)->post(route('billing.resume'))->assertRedirect(route('billing.index'));

    $session = json_encode(session()->all());
    expect($session)->not->toContain('sub_internal_secret')
        ->and($session)->not->toContain('req_abc123');
    Log::shouldHaveReceived('warning')->once();
});

it('resumes a cancelling local subscription and keeps its end date', function () {
    $company = Actors::company(subscribed: false);
    $grant = SubscriptionFactory::new()->forCompany($company)->create([
        'source' => 'admin',
        'stripe_subscription_id' => null,
        'cancel_at_period_end' => true,
        'ends_at' => now()->addDays(10),
        'current_period_end' => now()->addDays(10),
    ]);

    $this->actingAs($company->owner)->post(route('billing.resume'))->assertRedirect(route('billing.index'));

    // A grant never renews: it keeps its flag and end date, so it can never become lifetime access.
    expect($grant->fresh()->ends_at->isSameDay(now()->addDays(10)))->toBeTrue()
        ->and($grant->fresh()->cancel_at_period_end)->toBeTrue();
});

it('forbids cancel and resume without billing.manage', function (string $route) {
    $company = Actors::company();

    $this->actingAs(Actors::companyAdmin($company))->post(route($route))->assertForbidden();
})->with(['billing.cancel', 'billing.resume', 'billing.portal']);
