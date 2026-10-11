<?php

use App\Models\Subscription;
use Database\Factories\SubscriptionFactory;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Support\Actors;

/*
| PO-22 (2026-10-11): ended free trials stayed stripe_status = 'trialing' forever. A daily job
| marks them canceled; access was already refused by the end date.
*/

function expiredTrial(): Subscription
{
    return SubscriptionFactory::new()->trial()->create([
        'user_id' => Actors::companyOwner(subscribed: false)->id,
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);
}

it('marks ended trials canceled and leaves running trials and paid subscriptions alone', function () {
    $ended = expiredTrial();
    $running = SubscriptionFactory::new()->trial(5)->create(['user_id' => Actors::companyOwner(subscribed: false)->id]);
    $paid = Actors::subscribe(Actors::companyOwner(subscribed: false), ['ends_at' => now()->subDay(), 'stripe_subscription_id' => 'sub_paid']);
    $paidStatus = $paid->stripe_status;

    $this->artisan('subscriptions:close-expired-trials')->assertSuccessful();

    expect($ended->fresh()->stripe_status)->toBe('canceled')
        ->and($running->fresh()->stripe_status)->toBe('trialing')
        ->and($paid->fresh()->stripe_status)->toBe($paidStatus)
        ->and($ended->user->fresh()->hasActiveSubscription())->toBeFalse()
        ->and($ended->user->fresh()->hasUsedTrial())->toBeTrue();
});

it('changes nothing with --dry-run', function () {
    $ended = expiredTrial();

    $this->artisan('subscriptions:close-expired-trials', ['--dry-run' => true])
        ->expectsOutputToContain('1 expired trial(s) would be marked canceled.')
        ->assertSuccessful();

    expect($ended->fresh()->stripe_status)->toBe('trialing');
});

it('is scheduled daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'subscriptions:close-expired-trials'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 10 * * *');
});
