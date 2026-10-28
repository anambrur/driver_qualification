<?php

use App\Http\Middleware\EnsurePlatformSession;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Notifications\PlatformResetPasswordNotification;
use Database\Factories\DemoRequestFactory;
use Database\Factories\FaqFactory;
use Database\Factories\InspectionTemplateFactory;
use Database\Factories\PlanFactory;
use Database\Factories\PlatformUserFactory;
use Database\Factories\TestimonialFactory;
use Database\Seeders\CountryStateSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Support\Actors;

/*
| The platform area (04 §2-3, 11 §5 step 6): its own guard, login and short sessions; tenant
| users never reach it and platform staff never reach tenant routes; each platform permission
| opens only its own screens; every change is audited; every page is private.
*/

beforeEach(function () {
    Notification::fake();
});

describe('login and session', function () {
    it('logs staff in on the platform guard and audits it', function () {
        $staff = Actors::platformOwner();

        $this->post(route('platform.login.store'), ['email' => strtoupper($staff->email), 'password' => 'password'])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($staff, 'platform');
        expect($this->isAuthenticated('web'))->toBeFalse()
            ->and($staff->fresh()->last_login_at)->not->toBeNull()
            ->and(AuditLog::where('action', 'platform.login')->where('actor_id', $staff->id)->exists())->toBeTrue();
    });

    it('refuses a wrong password and an inactive account with the same message, and audits the attempt', function () {
        $staff = Actors::platformOwner();
        $inactive = PlatformUserFactory::new()->inactive()->create();

        $wrong = $this->post(route('platform.login.store'), ['email' => $staff->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $off = $this->post(route('platform.login.store'), ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors('email');

        expect(session('errors')->first('email'))->toBe(trans('auth.failed'))
            ->and($this->isAuthenticated('platform'))->toBeFalse()
            ->and(AuditLog::where('action', 'platform.login_failed')->count())->toBe(2);
    });

    it('logs out staff who were disabled or idle too long', function (Closure $prepare) {
        $staff = Actors::platformOwner();
        $session = $prepare($staff);

        $this->actingAs($staff, 'platform')->withSession($session)
            ->get(route('platform.dashboard'))
            ->assertRedirect(route('platform.login'));

        expect($this->isAuthenticated('platform'))->toBeFalse();
    })->with([
        'disabled' => [function ($staff) {
            $staff->forceFill(['status' => 'inactive'])->save();

            return [];
        }],
        'idle' => [fn () => [EnsurePlatformSession::LAST_SEEN => time() - (config('auth.platform_idle_minutes') + 1) * 60]],
    ]);

    it('never follows an intended tenant URL after a platform login', function () {
        $staff = Actors::platformOwner();

        $this->get(route('admin.vehicle.index'))->assertRedirect(route('login'));
        $this->post(route('platform.login.store'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('platform.dashboard'));
    });

    it('logs out and audits it', function () {
        $staff = Actors::platformOwner();

        $this->actingAs($staff, 'platform')->post(route('platform.logout'))->assertRedirect(route('platform.login'));

        expect($this->isAuthenticated('platform'))->toBeFalse()
            ->and(AuditLog::where('action', 'platform.logout')->exists())->toBeTrue();
    });

    it('sends a platform reset link, never a tenant one', function () {
        $staff = Actors::platformOwner();

        $this->post(route('platform.password.email'), ['email' => $staff->email])->assertSessionHas('status');

        Notification::assertSentTo($staff, PlatformResetPasswordNotification::class, fn ($n) => str_contains($n->toMail($staff)->actionUrl, '/platform/reset-password/'));
    });
});

describe('guards stay apart', function () {
    it('sends guests and tenant users to the platform login', function () {
        $this->get(route('platform.companies.index'))->assertRedirect(route('platform.login'));
        $this->actingAs(Actors::companyOwner())->get(route('platform.companies.index'))->assertRedirect(route('platform.login'));
    });

    it('gives platform staff no tenant access', function () {
        $this->actingAs(Actors::platformOwner(), 'platform');
        // actingAs() also made `platform` the default guard; a real request starts on `web`.
        Auth::shouldUse('web');

        $this->get(route('admin.driver.index'))->assertRedirect(route('login'));
    });

    it('keeps a tenant login in the same browser out of platform requests', function () {
        $this->actingAs(Actors::companyOwner())
            ->actingAs(Actors::platformOwner(), 'platform')
            ->get(route('platform.companies.index'))
            ->assertOk();
    });

    it('puts every /platform route behind auth:platform except login and reset', function () {
        $open = ['platform.login', 'platform.login.store', 'platform.password.request', 'platform.password.email', 'platform.password.reset', 'platform.password.store'];

        $unguarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'platform'))
            ->reject(fn ($route) => in_array($route->getName(), $open, true))
            ->reject(fn ($route) => in_array('auth:platform', $route->gatherMiddleware(), true) && in_array('platform', $route->gatherMiddleware(), true))
            ->map->uri();

        expect($unguarded->all())->toBe([]);
    });
});

it('opens each screen only to the roles that hold its permission', function (string $role, string $route, int $status) {
    $this->actingAs(Actors::platformStaff($role), 'platform')->get(route($route))->assertStatus($status);
})->with([
    ['platform_support', 'platform.companies.index', 200],
    ['platform_support', 'platform.plans.index', 403],
    ['platform_support', 'platform.staff.index', 403],
    ['platform_support', 'platform.audit.index', 403],
    ['platform_support', 'platform.asset-transfers.index', 403],
    ['auditor', 'platform.audit.index', 200],
    ['auditor', 'platform.subscriptions.payments', 200],
    ['auditor', 'platform.settings.site.index', 403],
    ['billing_manager', 'platform.subscriptions.payments', 200],
    ['billing_manager', 'platform.plans.index', 403],
    ['platform_admin', 'platform.plans.index', 200],
    ['platform_admin', 'platform.asset-transfers.index', 200],
    ['platform_admin', 'platform.lookups.fuel-types.index', 200],
    ['platform_admin', 'platform.lookups.inspection-templates.index', 200],
    ['platform_support', 'platform.lookups.inspection-templates.index', 403],
    ['platform_admin', 'platform.staff.index', 403],
    ['platform_admin', 'platform.content.landing.edit', 200],
    ['platform_admin', 'platform.roles.index', 403],
    ['platform_owner', 'platform.roles.index', 200],
    ['billing_manager', 'platform.content.testimonials.index', 403],
    ['platform_support', 'platform.content.demo-requests.index', 403],
    ['platform_owner', 'platform.staff.index', 200],
]);

it('renders every platform screen', function () {
    $company = Actors::company();
    $plan = PlanFactory::new()->create();
    $staff = Actors::platformSupport();
    $testimonial = TestimonialFactory::new()->create();
    $faq = FaqFactory::new()->create();
    DemoRequestFactory::new()->create();
    DemoRequestFactory::new()->handled()->create();

    $this->actingAs(Actors::platformOwner(), 'platform');

    foreach ([
        route('platform.dashboard'), route('platform.companies.index', ['search' => 'a', 'status' => 'active']),
        route('platform.companies.show', $company), route('platform.companies.create'), route('platform.companies.edit', $company),
        route('platform.plans.index'), route('platform.plans.create'), route('platform.plans.edit', $plan), route('platform.features.index'),
        route('platform.subscriptions.dashboard'), route('platform.subscriptions.index', ['open' => 1]), route('platform.subscriptions.payments'),
        route('platform.lookups.document-types.index'), route('platform.lookups.vehicle-types.index'), route('platform.lookups.vehicle-groups.index'),
        route('platform.lookups.fuel-types.index'), route('platform.lookups.equipment-types.index'), route('platform.lookups.maintenance-categories.index'),
        route('platform.lookups.inspection-templates.index'), route('platform.lookups.inspection-templates.edit', InspectionTemplateFactory::new()->withItems()->create()),
        route('platform.settings.site.index'), route('platform.settings.tawk.index'),
        route('platform.staff.index'), route('platform.staff.create'), route('platform.staff.edit', $staff),
        route('platform.roles.index'), route('platform.roles.create'),
        route('platform.audit.index', ['action' => 'platform.']), route('platform.asset-transfers.index'), route('platform.asset-transfers.create'),
        route('platform.content.landing.edit'), route('platform.content.testimonials.index'), route('platform.content.testimonials.create'),
        route('platform.content.testimonials.edit', $testimonial), route('platform.content.faqs.index'), route('platform.content.faqs.create'),
        route('platform.content.faqs.edit', $faq), route('platform.content.demo-requests.index'), route('platform.content.demo-requests.index', ['status' => 'handled']),
    ] as $url) {
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
});

it('keeps the platform login private (noindex)', function () {
    $this->get(route('platform.login'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('noindex', false);
});

describe('companies', function () {
    it('creates a company through the provisioner and emails the owner a reset link', function () {
        // The seeder calls two public APIs; fake them so the test never depends on the network.
        Http::preventStrayRequests();
        Http::fake([
            'restcountries.com/*' => Http::response([['name' => ['common' => 'United States'], 'cca2' => 'US', 'idd' => ['root' => '+1', 'suffixes' => ['']], 'currencies' => ['USD' => ['name' => 'US Dollar']]]]),
            'countriesnow.space/*' => Http::response(['data' => ['states' => [['name' => 'Texas', 'state_code' => 'TX']]]]),
        ]);
        $this->seed(CountryStateSeeder::class);

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.companies.store'), ['company_name' => 'Acme Freight', 'state_code' => 'tx', 'timezone' => 'America/New_York', 'owner_name' => 'Ann Owner', 'owner_email' => 'Ann@Acme.test'])
            ->assertSessionHasNoErrors();

        $company = Company::query()->where('company_name', 'Acme Freight')->sole();
        expect($company->owner->email)->toBe('ann@acme.test')
            ->and($company->state_code)->toBe('TX')
            ->and($company->timezone)->toBe('America/New_York')
            ->and(User::query()->where('company_id', $company->id)->where('user_type', 'support')->exists())->toBeTrue()
            ->and(AuditLog::where('action', 'company.created')->where('company_id', $company->id)->exists())->toBeTrue();

        // The emailed link proves the address, so the owner can start a trial right away
        expect($company->owner->hasVerifiedEmail())->toBeTrue();
        Notification::assertSentTo($company->owner, ResetPassword::class);
    });

    it('suspends a company with a reason, which logs its users out, and audits it', function () {
        $company = Actors::company();

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.companies.status', $company), ['status' => 'suspended', 'reason' => 'Unpaid invoices'])
            ->assertSessionHasNoErrors();

        expect($company->fresh()->status)->toBe('suspended')
            ->and($company->fresh()->status_reason)->toBe('Unpaid invoices')
            ->and(Arr::sortRecursive(AuditLog::where('action', 'company.status_changed')->where('company_id', $company->id)->value('changes')))
            ->toBe(Arr::sortRecursive(['from' => 'active', 'to' => 'suspended', 'reason' => 'Unpaid invoices']));

        $this->actingAs($company->owner, 'web')->get(route('admin.driver.index'))->assertRedirect(route('login'));
    });

    it('needs platform.companies.lifecycle to change a status', function () {
        $company = Actors::company();

        $this->actingAs(Actors::platformSupport(), 'platform')
            ->post(route('platform.companies.status', $company), ['status' => 'suspended', 'reason' => 'x'])
            ->assertForbidden();

        expect($company->fresh()->status)->toBe('active');
    });

    it('transfers ownership to an active user of the company named by email', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.companies.owner', $company), ['email' => strtoupper($admin->email)])
            ->assertSessionHasNoErrors();

        expect($company->fresh()->owner_user_id)->toBe($admin->id);
    });

    it('refuses a new owner from another company', function () {
        $company = Actors::company();

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.companies.owner', $company), ['email' => Actors::companyOwner()->email])
            ->assertSessionHasErrors('email');

        expect($company->fresh()->owner_user_id)->toBe($company->owner_user_id);
    });
});

describe('catalogue', function () {
    it('archives the current price when a new one is added for the same interval, and audits both', function () {
        $owner = Actors::platformOwner();
        $this->actingAs($owner, 'platform')
            ->post(route('platform.plans.store'), ['name' => 'Fleet', 'slug' => 'fleet', 'is_active' => '1', 'marketing_bullets' => "Up to 10 trucks\nSupport"])
            ->assertSessionHasNoErrors();
        $plan = Plan::query()->where('slug', 'fleet')->sole();

        foreach (['49.00', '59.00'] as $amount) {
            $this->post(route('platform.plans.prices.store', $plan), ['interval' => 'month', 'amount' => $amount, 'currency' => 'usd'])->assertSessionHasNoErrors();
        }

        $prices = PlanPrice::query()->where('plan_id', $plan->id)->orderBy('id')->get();
        expect($plan->marketing_bullets)->toBe(['Up to 10 trucks', 'Support'])
            ->and($prices)->toHaveCount(2)
            ->and($prices[0]->archived_at)->not->toBeNull()
            ->and([$prices[1]->amount, $prices[1]->currency, $prices[1]->archived_at])->toBe(['59.00', 'USD', null])
            ->and(AuditLog::where('action', 'plan_price.created')->count())->toBe(2)
            ->and(AuditLog::where('action', 'plan_price.archived')->count())->toBe(1);
    });

    it('shows the plan form again with its errors after a failed save', function () {
        PlanFactory::new()->create(['slug' => 'taken']);

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->from(route('platform.plans.create'))
            ->post(route('platform.plans.store'), ['name' => 'Fleet', 'slug' => 'taken', 'marketing_bullets' => "One\nTwo"])
            ->assertSessionHasErrors('slug');

        $this->get(route('platform.plans.create'))->assertOk()->assertSee("One\nTwo", false);
    });

    it('saves the plan feature matrix through PlanFeatureService', function () {
        $plan = PlanFactory::new()->create();

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.plans.features', $plan), ['features' => [
                'fleet' => ['enabled' => '1'],
                'max_vehicles' => ['enabled' => '1', 'limit_value' => '25'],
                'drivers' => ['enabled' => '0'],
            ]])
            ->assertSessionHasNoErrors();

        expect(DB::table('plan_features')->where('plan_id', $plan->id)->pluck('limit_value', 'feature_key')->all())
            ->toBe(['fleet' => null, 'max_vehicles' => 25])
            ->and(AuditLog::where('action', 'plan_feature.updated')->count())->toBe(2);
    });

    it('refuses to delete a plan that is in use', function () {
        $company = Actors::company();
        $plan = Actors::openSubscription($company)->planPrice->plan;

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->delete(route('platform.plans.destroy', $plan))
            ->assertSessionHasErrors('plan');

        expect($plan->fresh())->not->toBeNull();
    });

    it('changes a feature availability for every company at once', function () {
        $a = Actors::company();
        $b = Actors::company();

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.features.update', 'gps_tracking'), ['availability' => 'override_only'])
            ->assertSessionHasNoErrors();

        expect(DB::table('features')->where('key', 'gps_tracking')->value('availability'))->toBe('override_only')
            ->and($a->fresh()->entitlements_version)->toBe($a->entitlements_version + 1)
            ->and($b->fresh()->entitlements_version)->toBe($b->entitlements_version + 1)
            ->and(Arr::sortRecursive(AuditLog::where('action', 'feature.updated')->value('changes')))->toBe(Arr::sortRecursive(['feature' => 'gps_tracking', 'from' => 'general', 'to' => 'override_only']));
    });
});

describe('audit', function () {
    it('audits lookup and settings changes with the platform actor', function () {
        $owner = Actors::platformOwner();

        $this->actingAs($owner, 'platform')
            ->postJson(route('platform.lookups.fuel-types.store'), ['name' => 'Hydrogen'])
            ->assertOk();

        expect(AuditLog::where('action', 'lookup.created')->where('actor_type', 'platform_user')->where('actor_id', $owner->id)->exists())->toBeTrue();
    });

    it('redacts secrets from audit changes', function () {
        $log = AuditLog::recordSystem(null, 'test.redaction', null, ['password' => 'x', 'nested' => ['api_token' => 'y', 'ok' => 'z'], 'ssn' => '123']);

        expect(Arr::sortRecursive($log->fresh()->changes))->toBe(Arr::sortRecursive(['password' => '[redacted]', 'nested' => ['api_token' => '[redacted]', 'ok' => 'z'], 'ssn' => '[redacted]']));
    });

    it('filters the audit viewer and offers no way to change an entry', function () {
        AuditLog::recordSystem(7, 'company.status_changed');
        AuditLog::recordSystem(8, 'plan.created');

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->get(route('platform.audit.index', ['action' => 'company.']))
            ->assertOk()->assertSee('company.status_changed')->assertDontSee('plan.created');

        $writes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'platform/audit') && array_diff($route->methods(), ['GET', 'HEAD']) !== []);

        expect($writes)->toBeEmpty();
    });
});
