<?php

use App\Models\Subscription;
use App\Models\SubscriptionNotificationLog;
use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringSoonNotification;
use App\Services\Billing\AccessState;
use App\Services\Billing\SubscriptionNotificationService;
use Database\Factories\SubscriptionFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\SubscriptionLifecycle\SubscriptionLifecycle;
use Tests\Support\Actors;

beforeEach(function () {
    $this->travelTo(now()->setTime(9, 0));
    $this->sms = [];
    SubscriptionLifecycle::fakeVonage($this->sms);
});

function lifecycleTrial(int $daysLeft, array $companyAttributes = []): Subscription
{
    $company = Actors::company($companyAttributes, subscribed: false);

    return SubscriptionFactory::new()->forCompany($company)->trial($daysLeft)->create();
}

function ownerOf(Subscription $subscription): User
{
    return $subscription->company->owner;
}

/*
| SUB-01: the log row was written before notify(); a failed send was never retried.
*/
describe('SUB-01', function () {
    it('retries the reminder on the next run when sending throws', function () {
        $subscription = lifecycleTrial(7);
        $fail = true;
        Event::listen(NotificationSending::class, function () use (&$fail) {
            if ($fail) {
                throw new RuntimeException('mail transport down');
            }
        });

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();
        expect(ownerOf($subscription)->notifications()->count())->toBe(0);

        $fail = false;
        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        expect(ownerOf($subscription)->notifications()->count())->toBe(1)
            ->and(SubscriptionNotificationLog::where('subscription_id', $subscription->id)->count())->toBe(2);
    });

    it('keeps processing other subscriptions when one send throws', function () {
        $broken = lifecycleTrial(7);
        $healthy = lifecycleTrial(7);
        Event::listen(NotificationSending::class, function ($event) use ($broken) {
            if ($event->notifiable->is(ownerOf($broken))) {
                throw new RuntimeException('mail transport down');
            }
        });

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        expect(ownerOf($healthy)->notifications()->count())->toBe(1);
    });

    it('removes the activation log rows when notify() throws so a webhook retry resends it', function () {
        $subscription = SubscriptionFactory::new()->forCompany(Actors::company(subscribed: false))->create();
        $fail = true;
        Event::listen(NotificationSending::class, function () use (&$fail) {
            if ($fail) {
                throw new RuntimeException('mail transport down');
            }
        });
        $service = app(SubscriptionNotificationService::class);

        expect(fn () => $service->sendActivated($subscription))->toThrow(RuntimeException::class);
        expect(SubscriptionNotificationLog::count())->toBe(0);

        $fail = false;
        expect($service->sendActivated($subscription))->toBeTrue()
            ->and($service->sendActivated($subscription))->toBeFalse()
            ->and(ownerOf($subscription)->notifications()->count())->toBe(1);
    });
});

/*
| SUB-02: reminders only fired on an exact day match, so a missed scheduler day skipped them.
*/
describe('SUB-02', function () {
    beforeEach(fn () => Notification::fake());

    it('sends the 7-day reminder when the scheduler missed day 7', function () {
        $subscription = lifecycleTrial(6);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Notification::assertSentTo(ownerOf($subscription), SubscriptionExpiringSoonNotification::class,
            fn ($n) => $n->daysRemaining === 6);
    });

    it('sends each window once, not every day inside it', function () {
        $subscription = lifecycleTrial(7);

        $this->artisan('subscriptions:send-expiry-reminders');
        $this->travel(1)->days();
        $this->artisan('subscriptions:send-expiry-reminders');
        $this->travel(1)->days();
        $this->artisan('subscriptions:send-expiry-reminders');

        Notification::assertSentToTimes(ownerOf($subscription), SubscriptionExpiringSoonNotification::class, 1);
    });

    it('sends the 3-day reminder when day 3 was missed, without resending the 7-day one', function () {
        $subscription = lifecycleTrial(7);

        $this->artisan('subscriptions:send-expiry-reminders');
        $this->travel(5)->days(); // 2 days left; day 3 run never happened
        $this->artisan('subscriptions:send-expiry-reminders');

        Notification::assertSentToTimes(ownerOf($subscription), SubscriptionExpiringSoonNotification::class, 2);
        Notification::assertSentTo(ownerOf($subscription), SubscriptionExpiringSoonNotification::class,
            fn ($n) => $n->daysRemaining === 2);
    });

    it('sends the expired notice and closes the trial when the expiry-day run was missed', function () {
        $user = Actors::company(subscribed: false)->owner;
        $subscription = SubscriptionFactory::new()->forCompany($user->company)->trial()->create([
            'trial_ends_at' => now()->subDay()->setTime(15, 0),
            'current_period_end' => now()->subDay()->setTime(15, 0),
            'ends_at' => now()->subDay()->setTime(15, 0),
        ]);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Notification::assertSentTo($user, SubscriptionExpiredNotification::class);
        expect($subscription->fresh()->status)->toBe('canceled')
            ->and($subscription->fresh()->ended_at)->not->toBeNull();
    });

    it('does not send anything for a subscription that ended long ago', function () {
        $company = Actors::company(subscribed: false);
        SubscriptionFactory::new()->forCompany($company)->expired()->create([
            'ended_at' => now()->subDays(30),
            'ends_at' => now()->subDays(30),
            'current_period_end' => now()->subDays(30),
        ]);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('does not remind a healthy paid subscription', function () {
        SubscriptionFactory::new()->forCompany(Actors::company(subscribed: false))->create(['current_period_end' => now()->addDays(3)]);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    });
});

/*
| SUB-03: expiry SMS went to the raw, free-text company.phone; a failed send was never retried.
*/
describe('SUB-03', function () {
    beforeEach(fn () => Notification::fake());

    it('sends the SMS to the normalized number', function () {
        lifecycleTrial(3, ['phone' => '(209) 277-6341']);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        expect($this->sms)->toHaveCount(1)
            ->and($this->sms[0]->getTo())->toBe('12092776341');
    });

    it('does not send to an invalid or unsupported number', function (string $phone) {
        $subscription = lifecycleTrial(3, ['phone' => $phone]);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        expect($this->sms)->toBe([]);
        Notification::assertSentTo(ownerOf($subscription), SubscriptionExpiringSoonNotification::class);
    })->with([
        'free text' => 'call the office',
        'premium rate' => '900-555-1234',
        'unsupported country' => '+44 20 7946 0958',
    ]);

    it('retries the SMS on the next run after Vonage fails', function () {
        $sent = [];
        SubscriptionLifecycle::fakeVonage($sent, failures: 1);
        $subscription = lifecycleTrial(3, ['phone' => '2092776341']);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();
        expect($sent)->toBe([])
            ->and(SubscriptionNotificationLog::where('subscription_id', $subscription->id)->where('channel', 'sms')->exists())->toBeFalse();

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();
        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        expect($sent)->toHaveCount(1);
    });

    it('does not mark the SMS as sent when Vonage is not configured', function () {
        config(['services.vonage.api_key' => null]);
        $subscription = lifecycleTrial(1, ['phone' => '2092776341']);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        expect($this->sms)->toBe([])
            ->and(SubscriptionNotificationLog::where('subscription_id', $subscription->id)->where('channel', 'sms')->exists())->toBeFalse();
    });
});

/*
| 07 §9: notices go to the company's Owner (mail + database) and to companies.billing_email
| (mail), once per window; read-only starts at the end of the past-due grace.
*/
describe('recipients', function () {
    beforeEach(fn () => Notification::fake());

    it('sends the reminder to the Owner and to billing_email', function () {
        $subscription = lifecycleTrial(7, ['billing_email' => 'Accounts@Carrier.test']);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Notification::assertSentTo(ownerOf($subscription), SubscriptionExpiringSoonNotification::class);
        Notification::assertSentOnDemand(SubscriptionExpiringSoonNotification::class,
            fn ($n, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'accounts@carrier.test' && $channels === ['mail']);
        expect(SubscriptionNotificationLog::where('subscription_id', $subscription->id)->where('type', 'expiring_soon')->value('recipient_user_id'))
            ->toBe(ownerOf($subscription)->id);
    });

    it('does not send twice when billing_email is the owner\'s email', function () {
        $subscription = lifecycleTrial(7);
        $subscription->company->update(['billing_email' => ownerOf($subscription)->email]);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Notification::assertSentToTimes(ownerOf($subscription), SubscriptionExpiringSoonNotification::class, 1);
        Notification::assertNotSentTo(new AnonymousNotifiable, SubscriptionExpiringSoonNotification::class);
    });

    it('reminds no other company user', function () {
        $subscription = lifecycleTrial(7);
        $dispatcher = Actors::dispatcher($subscription->company);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Notification::assertNotSentTo($dispatcher, SubscriptionExpiringSoonNotification::class);
    });

    it('counts a past-due company down to the end of its grace, then sends the read-only notice', function () {
        $company = Actors::company(subscribed: false);
        $subscription = SubscriptionFactory::new()->forCompany($company)->pastDue()->create([
            'current_period_start' => now()->subDays(4),
            'current_period_end' => now()->addDays(26),
        ]);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();
        Notification::assertSentTo($company->owner, SubscriptionExpiringSoonNotification::class, fn ($n) => $n->daysRemaining === 3);

        $this->travel(3)->days();
        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();
        Notification::assertSentTo($company->owner, SubscriptionExpiredNotification::class);
        expect($subscription->fresh()->status)->toBe('past_due');
    });

    it('skips a suspended company', function () {
        $subscription = lifecycleTrial(7);
        Actors::suspended($subscription->company);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('keeps full access on the expiry day until the trial actually ends', function () {
        $company = Actors::company(subscribed: false);
        $trial = SubscriptionFactory::new()->forCompany($company)->trial()->create([
            'trial_ends_at' => now()->setTime(17, 0),
            'current_period_end' => now()->setTime(17, 0),
            'ends_at' => now()->setTime(17, 0),
        ]);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        expect($trial->fresh()->ended_at)->toBeNull()
            ->and(AccessState::for($company->fresh()))->toBe(AccessState::Full);
    });
});
