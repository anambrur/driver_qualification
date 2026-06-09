<?php

namespace App\Providers;

use App\Models\Subscription;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            return new Client(new Basic(
                (string) config('services.vonage.api_key', ''),
                (string) config('services.vonage.api_secret', ''),
            ));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tell Cashier to use our custom Subscription model
        // so that the plan() relationship is available everywhere.
        Cashier::useSubscriptionModel(Subscription::class);
    }
}
