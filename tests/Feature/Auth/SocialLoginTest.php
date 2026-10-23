<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\SiteSetting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\SocialLoginService;
use App\Services\Billing\AccessStateResolver;
use App\Support\Ui\Toast;
use Database\Factories\SubscriptionFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Support\Actors;

/*
| P1-3 (roadmap 1.1, requirement 1.4): Google and Facebook sign-in, sign-up and account linking.
| Branches of the callback: a known account, b existing tenant email, c platform email, d new email,
| e no email. A provider email never links itself to an existing account.
*/

function enableSocialLogin(array $providers = ['google', 'facebook']): void
{
    foreach ($providers as $provider) {
        config(["services.{$provider}.client_id" => "{$provider}-id", "services.{$provider}.client_secret" => "{$provider}-secret"]);
    }
    SiteSetting::getSettings()->update(collect($providers)->mapWithKeys(fn ($p) => ["{$p}_login_enabled" => true])->all());
}

function fakeProvider(string $provider, array $attributes = []): SocialiteUser
{
    $user = SocialiteUser::fake($attributes);
    Socialite::fake($provider, $user);

    return $user;
}

function pendingSignup(array $overrides = []): array
{
    return [SocialLoginService::PENDING => array_merge([
        'provider' => 'google',
        'provider_user_id' => 'g-new',
        'email' => 'new.owner@example.com',
        'name' => 'New Owner',
        'email_verified' => true,
        'expires_at' => now()->addMinutes(15)->getTimestamp(),
    ], $overrides)];
}

beforeEach(fn () => enableSocialLogin());

describe('switches', function () {
    it('redirects to the provider when it is enabled', function () {
        fakeProvider('google');

        $this->get(route('auth.social.redirect', 'google'))->assertRedirect('https://socialite.fake/google/authorize');
    });

    it('is a 404 when the keys are missing or the platform switch is off', function () {
        config(['services.google.client_id' => null]);
        SiteSetting::getSettings()->update(['facebook_login_enabled' => false]);

        $this->get(route('auth.social.redirect', 'google'))->assertNotFound();
        $this->get(route('auth.social.redirect', 'facebook'))->assertNotFound();
        $this->get(route('auth.social.callback', 'facebook'))->assertNotFound();
    });

    it('only accepts google and facebook', function () {
        $this->get('/auth/apple/redirect')->assertNotFound();
    });

    it('shows the buttons only for enabled providers', function () {
        SiteSetting::getSettings()->update(['facebook_login_enabled' => false]);

        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Continue with Google')
                ->assertDontSee('Continue with Facebook');
        }
    });

    it('hides the buttons when no provider is enabled', function () {
        SiteSetting::getSettings()->update(['google_login_enabled' => false, 'facebook_login_enabled' => false]);

        $this->get('/login')->assertOk()->assertDontSee('Continue with');
        $this->get('/register')->assertOk()->assertDontSee('Continue with');
    });
});

describe('a: known provider account', function () {
    it('logs in with the same checks and records the provider', function () {
        $user = Actors::companyOwner();
        SocialAccount::factory()->for($user)->create(['provider' => 'google', 'provider_user_id' => 'g-1']);
        fakeProvider('google', ['id' => 'g-1', 'email' => $user->email]);

        $this->get(route('auth.social.callback', 'google'))->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        expect($user->fresh()->last_login_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'auth.login')->where('actor_id', $user->id)->value('changes'))->toBe(['provider' => 'google']);
    });

    it('logs in without an email once the account is linked', function () {
        $user = Actors::companyOwner();
        SocialAccount::factory()->for($user)->create(['provider' => 'facebook', 'provider_user_id' => 'f-1']);
        fakeProvider('facebook', ['id' => 'f-1', 'email' => null]);

        $this->get(route('auth.social.callback', 'facebook'))->assertRedirect();

        $this->assertAuthenticatedAs($user);
    });

    it('logs in a driver-role user like the password login does', function () {
        $driver = Actors::driverUser();
        SocialAccount::factory()->for($driver)->create(['provider' => 'google', 'provider_user_id' => 'g-d']);
        fakeProvider('google', ['id' => 'g-d']);

        $this->get(route('auth.social.callback', 'google'));

        $this->assertAuthenticatedAs($driver);
    });

    it('refuses an inactive user', function () {
        $user = Actors::companyAdmin(null, ['status' => 'inactive']);
        SocialAccount::factory()->for($user)->create(['provider_user_id' => 'g-2']);
        fakeProvider('google', ['id' => 'g-2']);

        $this->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your account is inactive. Please contact administrator.']);

        $this->assertGuest();
        expect(AuditLog::query()->where('action', 'auth.login_failed')->where('actor_id', $user->id)->exists())->toBeTrue();
    });

    it('refuses a user of a company that is not active', function () {
        $company = Actors::suspended(Actors::company());
        SocialAccount::factory()->for($company->owner)->create(['provider_user_id' => 'g-3']);
        fakeProvider('google', ['id' => 'g-3']);

        $this->get(route('auth.social.callback', 'google'))
            ->assertSessionHasErrors(['email' => 'Your company account is not active. Please contact support.']);

        $this->assertGuest();
    });

    it('never logs in the support user', function () {
        $company = Actors::company();
        $support = User::query()->where('company_id', $company->id)->where('user_type', 'support')->firstOrFail();
        $support->forceFill(['status' => 'active'])->save();
        SocialAccount::factory()->for($support)->create(['provider_user_id' => 'g-4']);
        fakeProvider('google', ['id' => 'g-4']);

        $this->get(route('auth.social.callback', 'google'))->assertSessionHasErrors('email');

        $this->assertGuest();
    });
});

describe('b, c, e: refused without linking', function () {
    it('b: does not link an existing tenant email', function () {
        $user = Actors::companyOwner();
        fakeProvider('google', ['id' => 'g-new', 'email' => strtoupper($user->email)]);

        $this->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'An account with this email already exists. Sign in with your password, then connect Google from your profile.']);

        $this->assertGuest();
        expect(SocialAccount::query()->count())->toBe(0);
    });

    it('c: refuses a platform email with the same message (D3a)', function () {
        $staff = Actors::platformOwner();
        fakeProvider('google', ['id' => 'g-new', 'email' => $staff->email]);

        $this->get(route('auth.social.callback', 'google'))
            ->assertSessionHasErrors(['email' => 'An account with this email already exists. Sign in with your password, then connect Google from your profile.']);

        $this->assertGuest();
        expect(session(SocialLoginService::PENDING))->toBeNull();
    });

    it('e: refuses a provider profile without an email', function () {
        fakeProvider('facebook', ['id' => 'f-new', 'email' => null]);

        $this->get(route('auth.social.callback', 'facebook'))
            ->assertSessionHasErrors(['email' => 'Your Facebook account did not share an email address. Register with your email and a password instead.']);

        expect(session(SocialLoginService::PENDING))->toBeNull();
    });

    it('refuses an invalid state', function () {
        Socialite::fake('google', fn () => throw new InvalidStateException);

        $this->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Sign-in with Google failed. Please try again.']);

        $this->assertGuest();
    });
});

describe('d: new email', function () {
    it('keeps the profile, never the token, and shows the finish form', function () {
        fakeProvider('google', ['id' => 'g-new', 'email' => 'New.Owner@Example.com', 'name' => 'New Owner', 'email_verified' => true]);

        $this->get(route('auth.social.callback', 'google'))->assertRedirect(route('auth.social.finish', 'google'));

        $pending = session(SocialLoginService::PENDING);
        expect($pending)->toMatchArray(['provider' => 'google', 'provider_user_id' => 'g-new', 'email' => 'new.owner@example.com', 'email_verified' => true])
            ->and(json_encode($pending))->not->toContain('fake-token');

        $this->get(route('auth.social.finish', 'google'))->assertOk()
            ->assertSee('new.owner@example.com')
            ->assertSee('Company name');
    });

    it('creates the company through the registration path with a verified Google email', function () {
        $this->withSession(pendingSignup())
            ->post(route('auth.social.finish', 'google'), ['name' => 'New Owner', 'company_name' => 'Acme Trucking'])
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'new.owner@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        expect($user->email_verified_at)->not->toBeNull()
            ->and($user->password)->not->toBeEmpty()
            ->and(Company::query()->whereKey($user->company_id)->value('owner_user_id'))->toBe($user->id)
            ->and(Company::query()->whereKey($user->company_id)->value('company_name'))->toBe('Acme Trucking')
            ->and($user->socialAccounts()->sole()->only('provider', 'provider_user_id'))->toBe(['provider' => 'google', 'provider_user_id' => 'g-new'])
            ->and(AuditLog::query()->where('action', 'company.created')->where('company_id', $user->company_id)->value('changes'))->toBe(['source' => 'social:google'])
            ->and(AuditLog::query()->where('action', 'auth.social_signup')->where('actor_id', $user->id)->exists())->toBeTrue()
            ->and(session(SocialLoginService::PENDING))->toBeNull();
    });

    it('leaves a Facebook email unverified and sends the verification email', function () {
        Notification::fake();

        $this->withSession(pendingSignup(['provider' => 'facebook', 'provider_user_id' => 'f-new', 'email_verified' => false]))
            ->post(route('auth.social.finish', 'facebook'), ['name' => 'New Owner', 'company_name' => 'Acme Trucking']);

        $user = User::query()->where('email', 'new.owner@example.com')->firstOrFail();
        expect($user->email_verified_at)->toBeNull();
        Notification::assertSentTo($user, VerifyEmail::class);
    });

    it('validates the finish form', function () {
        $this->withSession(pendingSignup())
            ->post(route('auth.social.finish', 'google'), ['name' => 'New Owner', 'company_name' => ''])
            ->assertSessionHasErrors('company_name');

        expect(User::query()->where('email', 'new.owner@example.com')->exists())->toBeFalse();
    });

    it('sends a missing or expired sign-up back to register', function () {
        $this->get(route('auth.social.finish', 'google'))->assertRedirect(route('register'));

        $this->withSession(pendingSignup(['expires_at' => now()->subMinute()->getTimestamp()]))
            ->post(route('auth.social.finish', 'google'), ['name' => 'New Owner', 'company_name' => 'Acme'])
            ->assertRedirect(route('register'));

        $this->withSession(pendingSignup(['provider' => 'facebook']))
            ->get(route('auth.social.finish', 'google'))
            ->assertRedirect(route('register'));

        expect(User::query()->where('email', 'new.owner@example.com')->exists())->toBeFalse();
    });

    it('refuses a provider account linked between the callback and the finish form', function () {
        SocialAccount::factory()->create(['provider' => 'google', 'provider_user_id' => 'g-new']);

        $this->withSession(pendingSignup())
            ->post(route('auth.social.finish', 'google'), ['name' => 'New Owner', 'company_name' => 'Acme'])
            ->assertSessionHasErrors(['email' => 'This Google account is already connected. Sign in with it instead.']);

        $this->assertGuest();
        expect(User::query()->where('email', 'new.owner@example.com')->exists())->toBeFalse();
    });

    it('refuses an email taken between the callback and the finish form', function () {
        Actors::platformOwner(['email' => 'new.owner@example.com']);

        $this->withSession(pendingSignup())
            ->post(route('auth.social.finish', 'google'), ['name' => 'New Owner', 'company_name' => 'Acme'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        expect(User::query()->where('email', 'new.owner@example.com')->exists())->toBeFalse();
    });
});

describe('connected accounts', function () {
    it('connects a provider from the profile', function () {
        $user = Actors::companyOwner();
        fakeProvider('google', ['id' => 'g-link', 'email' => 'Other@Gmail.com']);

        $this->actingAs($user)->post(route('profile.social.connect', 'google'))
            ->assertRedirect('https://socialite.fake/google/authorize')
            ->assertSessionHas(SocialLoginService::CONNECT, $user->id);

        $this->get(route('auth.social.callback', 'google'))->assertRedirect(route('profile.edit'));

        expect($user->socialAccounts()->sole()->only('provider', 'provider_user_id', 'provider_email'))
            ->toBe(['provider' => 'google', 'provider_user_id' => 'g-link', 'provider_email' => 'other@gmail.com'])
            ->and(AuditLog::query()->where('action', 'auth.social_connected')->where('actor_id', $user->id)->exists())->toBeTrue();
    });

    it('refuses a provider account that is linked to another user', function () {
        SocialAccount::factory()->create(['provider' => 'google', 'provider_user_id' => 'g-taken']);
        $user = Actors::companyOwner();
        fakeProvider('google', ['id' => 'g-taken']);

        $this->actingAs($user)->withSession([SocialLoginService::CONNECT => $user->id])
            ->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('profile.edit'));

        expect($user->socialAccounts()->exists())->toBeFalse()
            ->and(session(Toast::SESSION_KEY))->toBe([['category' => 'error', 'message' => 'This Google account is already connected to another login.']]);
    });

    it('ignores a callback without a connect request from a logged-in user', function () {
        $user = Actors::companyOwner();
        fakeProvider('google', ['id' => 'g-link']);

        $this->actingAs($user)->get(route('auth.social.callback', 'google'))->assertRedirect(route('dashboard'));

        expect($user->socialAccounts()->exists())->toBeFalse();
    });

    it('disconnects a provider', function () {
        $user = Actors::companyOwner();
        SocialAccount::factory()->for($user)->create(['provider' => 'google']);

        $this->actingAs($user)->delete(route('profile.social.disconnect', 'google'))->assertRedirect(route('profile.edit'));

        expect($user->socialAccounts()->exists())->toBeFalse()
            ->and(AuditLog::query()->where('action', 'auth.social_disconnected')->where('actor_id', $user->id)->exists())->toBeTrue();
    });

    it('keeps the last provider of a user who never set a password', function () {
        $user = Actors::companyOwner();
        $account = SocialAccount::factory()->for($user)->create(['provider' => 'google']);
        AuditLog::record($user, $user->company_id, 'auth.social_signup', $account);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('Connected accounts')
            ->assertSee("You haven't set a password yet")
            ->assertSee('Forgot password?');

        $this->delete(route('profile.social.disconnect', 'google'));
        expect($user->socialAccounts()->exists())->toBeTrue();

        AuditLog::record($user, $user->company_id, 'auth.password_reset');
        $this->delete(route('profile.social.disconnect', 'google'));
        expect($user->socialAccounts()->exists())->toBeFalse();
    });

    it('is not reachable in a support session', function () {
        $company = Actors::company();
        Actors::inSupportSession($company);

        $this->post(route('profile.social.connect', 'google'))->assertForbidden();
        $this->delete(route('profile.social.disconnect', 'google'))->assertForbidden();
    });
});

describe('platform settings', function () {
    it('saves the provider switches and audits them', function () {
        SiteSetting::getSettings()->update(['google_login_enabled' => false, 'facebook_login_enabled' => false]);

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.settings.site.update'), [
                'site_name' => 'DriverFilesHub', 'trial_days' => 14, 'grace_days' => 7, 'support_session_max_minutes' => 60,
                'google_login_enabled' => '1', 'facebook_login_enabled' => '0',
            ])
            ->assertSessionHasNoErrors();

        $setting = SiteSetting::query()->firstOrFail();
        expect($setting->google_login_enabled)->toBeTrue()
            ->and($setting->facebook_login_enabled)->toBeFalse()
            ->and(AuditLog::query()->where('action', 'site_settings.updated')->latest('id')->value('changes'))->toHaveKey('google_login_enabled');
    });
});

it('signs up with Google, reaches the dashboard, and signs in again with the same account (smoke)', function () {
    fakeProvider('google', ['id' => 'g-smoke', 'email' => 'smoke.owner@example.com', 'name' => 'Smoke Owner', 'email_verified' => true]);

    $this->get(route('auth.social.callback', 'google'))->assertRedirect(route('auth.social.finish', 'google'));
    $this->post(route('auth.social.finish', 'google'), ['name' => 'Smoke Owner', 'company_name' => 'Smoke Trucking'])
        ->assertRedirect(route('dashboard', absolute: false));
    // A new company picks a plan first, as after an email sign-up
    $this->get(route('dashboard'))->assertRedirect(route('pricing.plans'));
    $this->get(route('pricing.plans'))->assertOk();

    $user = User::query()->where('email', 'smoke.owner@example.com')->sole();
    SubscriptionFactory::new()->forCompany($user->company)->create();
    app(AccessStateResolver::class)->forget($user->company_id);
    $this->post(route('logout'));
    $this->assertGuest();

    fakeProvider('google', ['id' => 'g-smoke', 'email' => 'smoke.owner@example.com']);
    $this->get(route('auth.social.callback', 'google'))->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard'))->assertOk();
});
