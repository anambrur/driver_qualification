<?php

use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Tests\Support\Actors;

/*
| UI-12 (phase 2, batch 4b): every auth page on the shared x-layout.auth shell, with no Google Fonts or
| inline styles, the SEO split (login/register indexable, the rest noindex), and unchanged form behaviour.
*/

beforeEach(function () {
    config(['seo.index_environments' => ['testing'], 'app.url' => 'https://example.com']);
});

/** @return array<string, Closure(): string> */
function authPageUrls(): array
{
    return [
        'login' => fn () => route('login'),
        'register' => fn () => route('register'),
        'forgot password' => fn () => route('password.request'),
        'reset password' => fn () => route('password.reset', ['token' => 'reset-token', 'email' => 'jo@example.com']),
        'accept invitation' => function () {
            $owner = Actors::companyOwner();
            $token = str_repeat('a', 64);
            app(TenantContext::class)->run($owner->company_id, fn () => CompanyInvitation::create([
                'email' => 'invitee@example.com',
                'role_id' => Role::query()->where('team_id', $owner->company_id)->value('id'),
                'token_hash' => CompanyInvitation::hashToken($token),
                'invited_by_user_id' => $owner->id,
                'expires_at' => now()->addDay(),
            ]));

            return route('invitations.show', $token);
        },
        'verify email' => function () {
            test()->actingAs(User::factory()->unverified()->create());

            return route('verification.notice');
        },
        'confirm password' => function () {
            test()->actingAs(User::factory()->create());

            return route('password.confirm');
        },
        'platform login' => fn () => route('platform.login'),
        'platform forgot password' => fn () => route('platform.password.request'),
        'platform reset password' => fn () => route('platform.password.reset', ['token' => 'reset-token', 'email' => 'staff@example.com']),
    ];
}

dataset('auth pages', fn () => authPageUrls());

dataset('private auth pages', fn () => collect(authPageUrls())->except(['login', 'register'])->all());

it('renders every auth page on the shared layout without Google Fonts or inline styles', function (Closure $url) {
    $html = $this->get($url())->assertOk()
        ->assertSee('data-layout="auth"', false)
        ->assertSee('aria-label="Change colour theme"', false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('fonts.bunny.net', false)
        ->getContent();

    // The only <style> allowed is the brand-token block from partials/theme-head.
    preg_match_all('~<style[^>]*>(.*?)</style>~s', $html, $styles);
    expect($styles[1])->toHaveCount(1)
        ->and($styles[1][0])->toStartWith(':root{--brand:')
        ->and(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->not->toContain('style="');
})->with('auth pages');

it('keeps login and register public with a canonical and a description', function (string $route) {
    $this->get(route($route))->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
        ->assertSee('<link rel="canonical" href="https://example.com/'.$route.'">', false)
        ->assertSee('<meta name="description" content="', false)
        ->assertSee('property="og:title"', false);
})->with(['login', 'register']);

it('marks every other auth page noindex by header and meta', function (Closure $url) {
    $this->get($url())->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('rel="canonical"', false);
})->with('private auth pages');

it('shows login errors and keeps the email but never the password', function () {
    $user = User::factory()->create();

    $this->from(route('login'))->followingRedirects()
        ->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertOk()
        ->assertSee(trans('auth.failed'))
        ->assertSee('value="'.$user->email.'"', false)
        ->assertDontSee('wrong-password');
});

it('shows register errors and keeps the old input', function () {
    $this->from(route('register'))->followingRedirects()
        ->post(route('register'), [
            'name' => 'Jo Driver', 'company_name' => 'Acme Trucking', 'email' => 'not-an-email',
            'password' => 'secret-pass', 'password_confirmation' => 'other-pass',
        ])
        ->assertOk()
        ->assertSee('id="f-email-error"', false)
        ->assertSee('id="f-password-error"', false)
        ->assertSee(['value="Jo Driver"', 'value="Acme Trucking"', 'value="not-an-email"'], false)
        ->assertDontSee('secret-pass');
});

it('keeps every form field, action and csrf token', function (string $route, array $fields) {
    $response = $this->get(route($route))->assertOk()
        ->assertSee('name="_token"', false)
        ->assertSee('action="'.route($route === 'platform.login' ? 'platform.login.store' : $route).'"', false);

    foreach ($fields as $field) {
        $response->assertSee('name="'.$field.'"', false);
    }
})->with([
    'login' => ['login', ['email', 'password', 'remember']],
    'register' => ['register', ['name', 'company_name', 'email', 'password', 'password_confirmation']],
    'platform login' => ['platform.login', ['email', 'password']],
]);

it('shows the status message on the forgot password page', function () {
    $this->withSession(['status' => 'If that email has an account, a reset link is on its way.'])
        ->get(route('password.request'))
        ->assertOk()
        ->assertSee('If that email has an account, a reset link is on its way.');
});

it('shows the verification notice instead of the raw status code', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->withSession(['status' => 'verification-link-sent'])
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('A new verification link has been sent')
        ->assertDontSee('verification-link-sent');
});

it('keeps the platform login on the platform guard', function () {
    $staff = Actors::platformOwner();

    $this->get(route('platform.login'))->assertOk()
        ->assertSee('action="'.route('platform.login.store').'"', false);

    $this->post(route('platform.login.store'), ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('platform.dashboard'));

    $this->assertAuthenticatedAs($staff, 'platform');
    expect($this->isAuthenticated('web'))->toBeFalse();
});

it('escapes the company name once in the invitation heading', function () {
    $owner = Actors::companyOwner();
    $owner->company->forceFill(['company_name' => 'A & B Trucking'])->save();
    $token = str_repeat('b', 64);
    app(TenantContext::class)->run($owner->company_id, fn () => CompanyInvitation::create([
        'email' => 'invitee@example.com',
        'role_id' => Role::query()->where('team_id', $owner->company_id)->value('id'),
        'token_hash' => CompanyInvitation::hashToken($token),
        'invited_by_user_id' => $owner->id,
        'expires_at' => now()->addDay(),
    ]));

    $this->get(route('invitations.show', $token))->assertOk()
        ->assertSee('<h1 class="text-2xl font-semibold tracking-tight">Join A &amp; B Trucking</h1>', false)
        ->assertDontSee('&amp;amp;', false);
});
