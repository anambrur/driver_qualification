<?php

namespace App\Providers;

use App\Authorization\CatalogueRegistrar;
use App\Authorization\PermissionGate;
use App\Authorization\TenantPermissionResolver;
use App\Models\User;
use App\Services\Billing\AccessStateResolver;
use App\Services\Entitlements\EntitlementResolver;
use App\Services\Entitlements\Entitlements;
use App\Services\Mail\MailIdentity;
use App\Services\Maps\Geocoder;
use App\Services\Maps\NominatimGeocoder;
use App\Services\Maps\NullGeocoder;
use App\Services\Maps\PhotonGeocoder;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Mime\Address;
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
        $this->app->scoped(EntitlementResolver::class);

        // Address search (config/maps.php); anything else than a known driver turns search off
        $this->app->bind(Geocoder::class, fn () => match (config('maps.geocoder.driver')) {
            'photon' => new PhotonGeocoder,
            'nominatim' => new NominatimGeocoder,
            default => new NullGeocoder,
        });

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

        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::before(fn ($user, string $ability) => $this->app->make(PermissionGate::class)->before($user, $ability));

        // Display only (05 §4): routes use feature: middleware and Gate::before.
        Blade::if('feature', function (string $feature) {
            $user = Auth::user();

            return $user instanceof User && $user->company && Entitlements::for($user->company)->allows($feature);
        });

        // Platform mail and notifications reply to the platform; company mail sets its own Reply-To (MailIdentity)
        Event::listen(function (MessageSending $event) {
            if ($event->message->getReplyTo() !== []) {
                return;
            }

            $platform = app(MailIdentity::class)->platformReplyTo();

            if ($platform) {
                $event->message->replyTo(new Address($platform->address, (string) $platform->name));
            }
        });
    }
}
