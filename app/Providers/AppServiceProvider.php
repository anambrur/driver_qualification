<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Events\SubscriptionExpired;
use App\Events\SubscriptionExpiringSoon;
use App\Notifications\SubscriptionExpiredNotification;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SubscriptionExpired::class, function ($event) {
            $event->subscription->user->notify(new SubscriptionExpiredNotification($event->subscription));
        });

        Event::listen(SubscriptionExpiringSoon::class, function ($event) {
            // Send expiry warning email
            // $event->subscription->user->notify(new SubscriptionExpiringSoonNotification($event->subscription, $event->daysRemaining));
        });
    }
}
