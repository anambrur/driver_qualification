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
    $user = Actors::companyOwner(subscribed: false);
    SubscriptionFactory::new()->cancelingAtPeriodEnd()->create(['user_id' => $user->id]);

    $client = StripeFake::bindClient();
    $client->subscriptions = Mockery::mock(SubscriptionService::class);
    $client->subscriptions->shouldReceive('update')
        ->andThrow(InvalidRequestException::factory('No such subscription: sub_internal_secret; request req_abc123'));

    $this->actingAs($user)->post(route('billing.resume'))->assertRedirect(route('billing.index'));

    $session = json_encode(session()->all());
    expect($session)->not->toContain('sub_internal_secret')
        ->and($session)->not->toContain('req_abc123');
    Log::shouldHaveReceived('warning')->once();
});

it('resumes a subscription on its grace period', function () {
    $user = Actors::companyOwner(subscribed: false);
    $subscription = SubscriptionFactory::new()->trial()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('billing.resume'))->assertRedirect(route('billing.index'));

    expect($subscription->fresh()->cancel_at_period_end)->toBeFalse();
});
