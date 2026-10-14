<?php

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| BIL-03 and 07 §5: one free trial per company and per verified owner email (PO-15), on the
| trial plan in site_settings (Q5: the top tier). companies.trial_email is unique, so an email
| change does not free the email for a second trial.
*/

beforeEach(function () {
    Notification::fake();
    $this->trialPrice = Billing::trialPlan(14);
});

it('requires login to start a trial', function () {
    $this->post(route('billing.trial'))->assertRedirect(route('login'));

    expect(Subscription::count())->toBe(0);
});

it('starts a 14-day trialing subscription on the trial plan for the company', function () {
    $company = Actors::company(subscribed: false);

    $this->actingAs($company->owner)->post(route('billing.trial'))->assertRedirect(route('checkout.success'));

    $trial = $company->subscriptions()->sole();
    expect($trial->status)->toBe('trialing')
        ->and($trial->source)->toBe('trial')
        ->and($trial->plan_price_id)->toBe($this->trialPrice->id)
        ->and($trial->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and($company->fresh()->trial_used_at)->not->toBeNull()
        ->and($company->fresh()->trial_email)->toBe($company->owner->email);
});

it('refuses a second trial in the same company (acceptance)', function () {
    $company = Actors::company(subscribed: false);

    $this->actingAs($company->owner)->post(route('billing.trial'))->assertRedirect(route('checkout.success'));
    $this->travel(15)->days();
    $this->artisan('subscriptions:close-expired-trials');
    $this->actingAs($company->owner)->post(route('billing.trial'))->assertRedirect(route('pricing.plans'));

    expect($company->subscriptions()->where('source', 'trial')->count())->toBe(1);
});

it('refuses a second trial started by another billing manager of the same company', function () {
    $company = Actors::company(subscribed: false);
    $this->actingAs($company->owner)->post(route('billing.trial'));
    $this->travel(15)->days();
    $manager = Actors::memberWithPermissions($company, ['billing.manage', 'billing.view'], ['email_verified_at' => now()]);

    $this->actingAs($manager)->post(route('billing.trial'))->assertRedirect(route('pricing.plans'));

    expect(Subscription::where('source', 'trial')->count())->toBe(1);
});

it('refuses a trial in another company with the same owner email, even after an email change (acceptance)', function () {
    $first = Actors::company(subscribed: false);
    $email = $first->owner->email;
    $this->actingAs($first->owner)->post(route('billing.trial'))->assertRedirect(route('checkout.success'));

    // The first owner moves to a new address; a new company's owner takes the old one.
    $first->owner->update(['email' => 'moved@example.test']);
    $second = Actors::company(subscribed: false);
    $second->owner->update(['email' => $email]);

    $this->actingAs($second->owner->fresh())->post(route('billing.trial'))->assertRedirect(route('pricing.plans'));

    expect(Subscription::where('source', 'trial')->count())->toBe(1)
        ->and($second->subscriptions()->exists())->toBeFalse();
});

it('gives each verified email one trial (decided PO-15, 2026-10-11)', function () {
    $first = Actors::company(subscribed: false);
    $second = Actors::company(subscribed: false);

    $this->actingAs($first->owner)->post(route('billing.trial'))->assertRedirect(route('checkout.success'));
    $this->actingAs($second->owner)->post(route('billing.trial'))->assertRedirect(route('checkout.success'));

    expect(Subscription::where('source', 'trial')->count())->toBe(2);
});

it('keeps the trial record when a later paid Stripe subscription is synced', function () {
    $company = Actors::company(subscribed: false);
    $this->actingAs($company->owner)->post(route('billing.trial'));
    $trial = $company->subscriptions()->sole();

    $price = Billing::price();
    $stripeSub = Billing::stripeSubscription($company, $price);
    Billing::fakeStripe($stripeSub);
    Billing::postWebhook($this, 'customer.subscription.created', $stripeSub)->assertOk();

    expect($trial->fresh()->source)->toBe('trial')
        ->and($trial->fresh()->ended_at)->not->toBeNull()
        ->and($trial->fresh()->stripe_subscription_id)->toBeNull()
        ->and($company->subscriptions()->open()->sole()->stripe_subscription_id)->toBe($stripeSub['id'])
        ->and($company->fresh()->trial_used_at)->not->toBeNull();
});

it('refuses a trial while the company already pays', function () {
    $company = Actors::company();

    $this->actingAs($company->owner)->post(route('billing.trial'))->assertRedirect(route('pricing.plans'));

    expect($company->subscriptions()->where('source', 'trial')->exists())->toBeFalse();
});

it('refuses a trial to an account whose email is not verified (decided PO-15)', function () {
    $company = Actors::company(subscribed: false);
    $company->owner->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($company->owner)->post(route('billing.trial'))->assertRedirect(route('verification.notice'));

    expect(Subscription::count())->toBe(0);
});

it('forbids a member without billing.manage', function () {
    $company = Actors::company(subscribed: false);

    $this->actingAs(Actors::dispatcher($company))->post(route('billing.trial'))->assertForbidden();

    expect(Subscription::count())->toBe(0);
});

it('sends a verification email on registration, and the new account verifies before its trial', function () {
    $this->post('/register', [
        'name' => 'New Carrier',
        'company_name' => 'New Carrier LLC',
        'email' => 'new-carrier@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'new-carrier@example.test')->sole();
    Notification::assertSentTo($user, VerifyEmail::class);

    // Unverified: no trial, and /dashboard asks for verification first
    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->post(route('billing.trial'))->assertRedirect(route('verification.notice'));

    $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // Verified, no plan yet: /dashboard sends them to pricing (PO-16), the trial works
    $this->get(route('dashboard'))->assertRedirect(route('pricing.plans'));
    $this->post(route('billing.trial'))->assertRedirect(route('checkout.success'));
    $this->get(route('dashboard'))->assertOk();
});
