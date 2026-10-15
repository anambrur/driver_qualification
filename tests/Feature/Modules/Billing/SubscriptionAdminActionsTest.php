<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionNotificationService;
use App\Services\Stripe\StripeClientFactory;
use Database\Factories\SubscriptionFactory;
use Illuminate\Testing\TestResponse;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\SubscriptionService;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;
use Tests\Support\StripeFake;

/*
| SADM-03, re-keyed by company (07 §7): grant / expire / suspend / reactivate on local rows
| (admin grant, trial) never reach Stripe and always leave a time-bounded state. Grant is
| refused while the company has an accessible subscription. Only platform staff with
| platform.subscriptions.manage can act; every action is audited.
*/

beforeEach(function () {
    $this->freezeSecond();

    $factory = Mockery::mock(StripeClientFactory::class);
    $factory->shouldNotReceive('make');
    app()->instance(StripeClientFactory::class, $factory);

    $this->notifications = Mockery::mock(SubscriptionNotificationService::class);
    $this->notifications->shouldReceive('sendActivated')->byDefault();
    app()->instance(SubscriptionNotificationService::class, $this->notifications);

    $this->staff = Actors::platformOwner();
    $this->company = Actors::company(subscribed: false);
    $this->price = Billing::price();
});

function sadmPost(string $action, Company $company, array $data = []): TestResponse
{
    return test()->actingAs(test()->staff, 'platform')
        ->from('/platform')
        ->post(route("platform.companies.subscription.{$action}", $company), $data);
}

function sadmGrant(Company $company, int $days = 30): Subscription
{
    sadmPost('grant', $company, ['plan_price_id' => test()->price->id, 'ends_at' => now()->addDays($days)->toDateString()])
        ->assertRedirect('/platform');
    expect(json_encode(session()->all()))->toContain('Complimentary subscription granted.');

    return $company->subscriptions()->open()->firstOrFail();
}

it('grants a time-bounded complimentary subscription and audits it', function () {
    $sub = sadmGrant($this->company);

    expect($sub->source)->toBe('admin')
        ->and($sub->stripe_subscription_id)->toBeNull()
        ->and($sub->isAccessible())->toBeTrue()
        ->and($sub->accessEndsAt()->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and(AuditLog::where('action', 'subscription.granted')->where('actor_type', 'platform_user')->where('company_id', $this->company->id)->exists())->toBeTrue();

    $this->travel(31)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('refuses an open-ended or past grant', function () {
    sadmPost('grant', $this->company, ['plan_price_id' => $this->price->id])->assertSessionHasErrors('ends_at');
    sadmPost('grant', $this->company, ['plan_price_id' => $this->price->id, 'ends_at' => now()->subDay()->toDateString()])->assertSessionHasErrors('ends_at');

    expect($this->company->subscriptions()->exists())->toBeFalse();
});

it('refuses to grant while the company has an accessible subscription', function (string $state) {
    $factory = SubscriptionFactory::new()->forCompany($this->company);
    $factory = match ($state) {
        'active stripe' => $factory,
        'trial' => $factory->trial(),
        'past_due in grace' => $factory->pastDue(),
        'canceling, time left' => $factory->cancelingAtPeriodEnd(),
        default => throw new InvalidArgumentException($state),
    };
    $factory->create();

    $this->notifications->shouldNotReceive('sendActivated');

    sadmPost('grant', $this->company, ['plan_price_id' => $this->price->id, 'ends_at' => now()->addDays(30)->toDateString()])
        ->assertRedirect('/platform');

    expect(json_encode(session()->all()))->toContain('Expire it first')
        ->and($this->company->subscriptions()->count())->toBe(1);
})->with(['active stripe', 'trial', 'past_due in grace', 'canceling, time left']);

it('allows a grant once the previous subscription has lapsed, keeping it as history', function () {
    $old = SubscriptionFactory::new()->forCompany($this->company)->create(['status' => 'unpaid']);

    expect(sadmGrant($this->company)->isAccessible())->toBeTrue()
        ->and($old->fresh()->ended_at)->not->toBeNull()
        ->and($this->company->subscriptions()->count())->toBe(2);
});

it('expires an admin grant locally without calling Stripe', function () {
    $sub = sadmGrant($this->company);

    sadmPost('expire', $this->company)->assertRedirect('/platform');

    expect($sub->fresh()->status)->toBe('canceled')
        ->and($sub->fresh()->ended_at)->not->toBeNull()
        ->and($sub->fresh()->isAccessible())->toBeFalse();
});

it('expires a trial locally without calling Stripe', function () {
    $sub = SubscriptionFactory::new()->forCompany($this->company)->trial()->create();

    sadmPost('expire', $this->company)->assertRedirect('/platform');

    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('suspends an admin grant until its original end date', function () {
    $sub = sadmGrant($this->company);
    $endsAt = $sub->accessEndsAt();

    sadmPost('suspend', $this->company)->assertRedirect('/platform');

    $fresh = $sub->fresh();
    expect($fresh->cancel_at_period_end)->toBeTrue()
        ->and($fresh->accessEndsAt()->equalTo($endsAt))->toBeTrue();

    $this->travel(31)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('reactivating a suspended admin grant keeps its original end date', function () {
    $sub = sadmGrant($this->company);
    $endsAt = $sub->accessEndsAt();

    sadmPost('suspend', $this->company);
    sadmPost('reactivate', $this->company)->assertRedirect('/platform');

    $fresh = $sub->fresh();
    expect($fresh->status)->toBe('active')
        ->and($fresh->isAccessible())->toBeTrue()
        ->and($fresh->accessEndsAt()?->equalTo($endsAt))->toBeTrue();

    $this->travel(31)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('reactivating a suspended trial keeps the trial end date', function () {
    $sub = SubscriptionFactory::new()->forCompany($this->company)->trial(7)->create();

    sadmPost('suspend', $this->company);
    sadmPost('reactivate', $this->company);

    expect($sub->fresh()->status)->toBe('trialing')
        ->and($sub->fresh()->isAccessible())->toBeTrue();

    $this->travel(8)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('refuses to reactivate an expired local subscription', function () {
    $sub = sadmGrant($this->company);
    sadmPost('expire', $this->company);

    sadmPost('reactivate', $this->company)->assertRedirect('/platform');

    expect($sub->fresh()->status)->toBe('canceled')
        ->and($sub->fresh()->isAccessible())->toBeFalse();
});

it('does not echo exception messages when reactivate fails', function () {
    SubscriptionFactory::new()->forCompany($this->company)->cancelingAtPeriodEnd()->create(['stripe_subscription_id' => 'sub_test_secret']);

    $client = StripeFake::bindClient();
    $client->subscriptions = Mockery::mock(SubscriptionService::class);
    $client->subscriptions->shouldReceive('update')
        ->andThrow(new InvalidRequestException('No such subscription: sub_test_secret; request req_internal_123'));

    sadmPost('reactivate', $this->company)->assertRedirect('/platform');

    $flashed = json_encode(session()->all());
    expect($flashed)->not->toContain('req_internal_123')
        ->and($flashed)->not->toContain('No such subscription');
});

it('a tenant cannot turn an admin grant into lifetime access via billing cancel + resume', function () {
    $sub = sadmGrant($this->company);

    $this->actingAs($this->company->owner)->post(route('billing.cancel'));
    $this->actingAs($this->company->owner)->post(route('billing.resume'));

    expect($sub->fresh()->isAccessible())->toBeTrue();

    $this->travel(31)->days();
    expect($sub->fresh()->isAccessible())->toBeFalse();
});

it('keeps tenant users out of the platform billing actions', function () {
    $this->actingAs($this->company->owner)
        ->post(route('platform.companies.subscription.grant', $this->company), ['plan_price_id' => $this->price->id, 'ends_at' => now()->addDays(30)->toDateString()])
        ->assertRedirect(route('platform.login'));

    expect($this->company->subscriptions()->exists())->toBeFalse();
});

it('forbids platform staff without platform.subscriptions.manage', function () {
    $this->actingAs(Actors::platformSupport(), 'platform')
        ->post(route('platform.companies.subscription.expire', $this->company))
        ->assertForbidden();
});
