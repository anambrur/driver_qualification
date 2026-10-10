<?php

use App\Models\Subscription;
use Database\Factories\PlanFactory;
use Database\Factories\SubscriptionFactory;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| BIL-03: free trials are granted once per user. Whether that should be once per company /
| per verified email is a product question (DEFERRED); these tests pin the per-user rule.
*/

beforeEach(function () {
    Notification::fake();
    $this->trialPlan = PlanFactory::new()->trial(14)->create();
});

it('requires login to start a trial', function () {
    $this->get(route('checkout', $this->trialPlan->slug))->assertRedirect(route('login'));

    expect(Subscription::count())->toBe(0);
});

it('starts a trial once and refuses a second one for the same user', function () {
    $user = Actors::companyOwner(subscribed: false);

    $this->actingAs($user)->get(route('checkout', $this->trialPlan->slug))->assertRedirect(route('checkout.success'));
    $this->travel(15)->days();
    $this->actingAs($user)->get(route('checkout', $this->trialPlan->slug))->assertRedirect(route('pricing.plans'));

    expect(Subscription::where('user_id', $user->id)->count())->toBe(1);
});

it('keeps the trial record when a later paid Stripe subscription is synced', function () {
    $user = Actors::companyOwner(subscribed: false);
    $plan = PlanFactory::new()->create();
    $trial = SubscriptionFactory::new()->trial()->create([
        'user_id' => $user->id,
        'plan_id' => $this->trialPlan->id,
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);

    $stripeSub = Billing::stripeSubscription($user, $plan);
    Billing::fakeStripe($stripeSub);
    Billing::postWebhook($this, 'customer.subscription.created', $stripeSub)->assertOk();

    expect($trial->fresh()->source)->toBe('trial')
        ->and($trial->fresh()->stripe_subscription_id)->toBeNull()
        ->and(Subscription::where('stripe_subscription_id', $stripeSub['id'])->exists())->toBeTrue()
        ->and($user->fresh()->hasUsedTrial())->toBeTrue();
});

it('refuses a trial after a paid subscription has replaced an earlier trial', function () {
    $user = Actors::companyOwner(subscribed: false);
    $plan = PlanFactory::new()->create();
    SubscriptionFactory::new()->trial()->create([
        'user_id' => $user->id,
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);

    $stripeSub = Billing::stripeSubscription($user, $plan, ['status' => 'canceled', 'ended_at' => now()->subHour()->timestamp]);
    Billing::fakeStripe($stripeSub);
    Billing::postWebhook($this, 'customer.subscription.created', $stripeSub)->assertOk();

    $this->actingAs($user)->get(route('checkout', $this->trialPlan->slug))->assertRedirect(route('pricing.plans'));

    expect(Subscription::where('user_id', $user->id)->where('source', 'trial')->count())->toBe(1);
});

it('currently gives a brand-new account its own trial (per-user rule, see DEFERRED BIL-03)', function () {
    $first = Actors::companyOwner(subscribed: false);
    $second = Actors::companyOwner(subscribed: false);

    $this->actingAs($first)->get(route('checkout', $this->trialPlan->slug))->assertRedirect(route('checkout.success'));
    $this->actingAs($second)->get(route('checkout', $this->trialPlan->slug))->assertRedirect(route('checkout.success'));

    expect(Subscription::where('source', 'trial')->count())->toBe(2);
});
