<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
        //
    }
}
