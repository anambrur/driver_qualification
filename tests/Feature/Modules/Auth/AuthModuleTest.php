<?php

use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Support\Actors;

afterEach(function () {
    Str::createRandomStringsNormally();
});

/*
| AUTH-02: login must not reveal that an email belongs to an inactive account
| unless the caller also knows the password.
*/
describe('AUTH-02 login enumeration', function () {
    it('returns the generic failure for an inactive account with a wrong password', function () {
        $user = User::factory()->create(['status' => 'inactive']);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    });

    it('gives the same error for an unknown email and an inactive email with a wrong password', function () {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'nope']);
        $inactiveError = session('errors')->first('email');

        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'nope']);
        $unknownError = session('errors')->first('email');

        expect($inactiveError)->toBe($unknownError);
    });

    it('tells an inactive user with the right password that the account is inactive and does not log them in', function () {
        $user = User::factory()->create(['status' => 'inactive']);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'Your account is inactive. Please contact administrator.']);
        $this->assertGuest();
    });

    it('still logs in an active user', function () {
        $user = User::factory()->create(['status' => 'active']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    });
});

/*
| AUTH-03: registration failures must roll back cleanly, show a generic message,
| and must not flash the plaintext password into the session.
*/
describe('AUTH-03 registration failure handling', function () {
    it('rolls back the user and company when creating the company roles fails', function () {
        Log::spy();
        Role::creating(fn () => throw new RuntimeException('role store unavailable'));

        $this->from('/register')->post('/register', [
            'name' => 'Acme Hauling',
            'company_name' => 'Acme Hauling LLC',
            'timezone' => 'America/Chicago',
            'email' => 'acme@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/register');

        $this->assertGuest();
        expect(User::where('email', 'acme@example.com')->exists())->toBeFalse()
            ->and(Company::where('email', 'acme@example.com')->exists())->toBeFalse();
        Log::shouldHaveReceived('error')->once();
    });

    it('does not flash the password into the session when registration fails', function () {
        // No company name: validation fails
        $this->from('/register')->post('/register', [
            'name' => 'Acme Hauling',
            'email' => 'acme@example.com',
            'password' => 'S3cret-pass!',
            'password_confirmation' => 'S3cret-pass!',
        ]);

        expect(session()->getOldInput('password'))->toBeNull()
            ->and(session()->getOldInput('password_confirmation'))->toBeNull()
            ->and(session()->getOldInput('email'))->toBe('acme@example.com');
    });

    it('creates the user, the company and its Owner role on success', function () {

        $this->post('/register', [
            'name' => 'Acme Hauling',
            'company_name' => 'Acme Hauling',
            'timezone' => 'America/Chicago',
            'email' => 'acme@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'acme@example.com')->firstOrFail();
        $ownerRole = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)->where('roles.team_id', $user->company_id)->value('roles.system_key');
        expect($ownerRole)->toBe('owner')
            ->and(Company::where('owner_user_id', $user->id)->count())->toBe(1);
        $this->assertAuthenticatedAs($user);
    });
});

/*
| AUTH-05: the company slug must be unique (companies.slug has a unique index since CMP-04).
*/
describe('AUTH-05 company slug', function () {
    it('picks a different slug when the random suffix collides with an existing company', function () {
        CompanyFactory::new()->create(['slug' => 'acme-hauling-aaaa']);
        // Only the 4-char slug suffix is forced; other Str::random() callers (session, tokens) stay random.
        $suffixes = ['aaaa', 'bbbb'];
        Str::createRandomStringsUsing(function (int $length) use (&$suffixes) {
            if ($length === 4 && $suffixes !== []) {
                return array_shift($suffixes);
            }

            return substr(bin2hex(random_bytes($length)), 0, $length);
        });

        $this->post('/register', [
            'name' => 'Acme Hauling',
            'company_name' => 'Acme Hauling',
            'timezone' => 'America/Chicago',
            'email' => 'acme@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        expect(User::where('email', 'acme@example.com')->firstOrFail()->company->slug)->toBe('acme-hauling-bbbb');
    });

    it('builds a usable slug when the name has no latin characters', function () {

        $this->post('/register', [
            'name' => '株式会社',
            'company_name' => '株式会社',
            'timezone' => 'America/Chicago',
            'email' => 'kk@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $slug = User::where('email', 'kk@example.com')->firstOrFail()->company->slug;
        expect($slug)->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/');
    });
});

/*
| AUTH-04: deleting your own profile deleted the company and, via FK cascades, all of its
| drivers. Decided PO-17 (2026-10-11): it deactivates the owner and the company instead, and
| only company owners may do it.
*/
describe('AUTH-04 closing an account', function () {
    it('deactivates the owner and the company and keeps its drivers (decided PO-17, 2026-10-11)', function () {
        $owner = Actors::companyOwner();
        $company = Company::where('owner_user_id', $owner->id)->firstOrFail();
        $driver = DriverFactory::new()->create(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->delete('/admin/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        expect($owner->fresh()->status)->toBe('inactive')
            ->and($company->fresh()->status)->toBe('deactivated')
            ->and(Driver::withoutTenancy()->find($driver->id))->not->toBeNull();
    });

    it('still needs the password', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)
            ->delete('/admin/profile', ['password' => 'wrong'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        expect($owner->fresh()->status)->toBe('active');
    });

    // Platform staff have no tenant profile at all (Platform/PlatformAreaTest)
    it('refuses users other than the owner', function () {
        $user = Actors::plainUser();

        $this->actingAs($user)
            ->delete('/admin/profile', ['password' => 'password'])
            ->assertForbidden();

        expect($user->fresh()->status)->toBe('active');
    });

    it('shows Close Account to owners only', function () {
        $this->actingAs(Actors::companyOwner())->get('/admin/profile')->assertOk()->assertSee('Close Account');
        $this->actingAs(Actors::plainUser())->get('/admin/profile')->assertOk()->assertDontSee('Close Account');
    });
});
