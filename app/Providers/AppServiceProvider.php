<?php

namespace App\Providers;

use App\Authorization\CatalogueRegistrar;
use App\Authorization\PermissionGate;
use App\Authorization\TenantPermissionResolver;
use App\Services\Billing\AccessStateResolver;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(TenantPermissionResolver::class);
        $this->app->scoped(AccessStateResolver::class);

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
        // Spatie binds its registrar while providers boot, so the override must come after (06 §2.6).
        $this->app->singleton(PermissionRegistrar::class, CatalogueRegistrar::class);

        Gate::before(fn ($user, string $ability) => $this->app->make(PermissionGate::class)->before($user, $ability));

        $replyTo = config('mail.reply_to.address');

        if (is_string($replyTo) && $replyTo !== '') {
            Mail::alwaysReplyTo(
                $replyTo,
                config('mail.reply_to.name') ?: null,
            );
        }
    }
}
