<?php

use App\Models\Subscription;
use App\Services\Billing\AccessState;
use Database\Factories\SubscriptionFactory;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Support\Actors;

/*
| PO-22 (2026-10-11) and 07 §4.1: an ended free trial has nothing in Stripe to end it. A daily
| job closes it (status canceled, ended_at); access already stopped at trial_ends_at.
*/

function expiredTrial(): Subscription
{
    return SubscriptionFactory::new()->forCompany(Actors::company(subscribed: false))->trial()->create([
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);
}

it('closes ended trials and leaves running trials and paid subscriptions alone', function () {
    $ended = expiredTrial();
    $running = SubscriptionFactory::new()->forCompany(Actors::company(subscribed: false))->trial(5)->create();
    $paid = SubscriptionFactory::new()->forCompany(Actors::company(subscribed: false))->create(['current_period_end' => now()->subDay()]);

    $this->artisan('subscriptions:close-expired-trials')->assertSuccessful();

    expect($ended->fresh()->status)->toBe('canceled')
        ->and($ended->fresh()->ended_at)->not->toBeNull()
        ->and($running->fresh()->status)->toBe('trialing')
        ->and($running->fresh()->ended_at)->toBeNull()
        ->and($paid->fresh()->status)->toBe('active')
        ->and($paid->fresh()->ended_at)->toBeNull()
        ->and(AccessState::for($ended->company))->toBe(AccessState::ReadOnly)
        ->and($ended->company->fresh()->trial_used_at === null)->toBeTrue();
});

it('changes nothing with --dry-run', function () {
    $ended = expiredTrial();

    $this->artisan('subscriptions:close-expired-trials', ['--dry-run' => true])
        ->expectsOutputToContain('1 expired trial(s) would be closed.')
        ->assertSuccessful();

    expect($ended->fresh()->status)->toBe('trialing');
});

it('is scheduled daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'subscriptions:close-expired-trials'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 10 * * *');
});
