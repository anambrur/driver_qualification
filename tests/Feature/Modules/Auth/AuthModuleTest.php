<?php

use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
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
    it('rolls back the user and company when the company role is missing', function () {
        Log::spy();

        $this->from('/register')->post('/register', [
            'name' => 'Acme Hauling',
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

    it('creates the user, company and company role on success', function () {
        Actors::seedAccessControl();

        $this->post('/register', [
            'name' => 'Acme Hauling',
            'email' => 'acme@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'acme@example.com')->firstOrFail();
        expect($user->hasRole('company'))->toBeTrue()
            ->and(Company::where('user_id', $user->id)->count())->toBe(1);
        $this->assertAuthenticatedAs($user);
    });
});

/*
| AUTH-05: the company slug must be unique (companies.slug has a unique index since CMP-04).
*/
describe('AUTH-05 company slug', function () {
    it('picks a different slug when the random suffix collides with an existing company', function () {
        Actors::seedAccessControl();
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
            'email' => 'acme@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        expect(Company::where('email', 'acme@example.com')->value('slug'))->toBe('acme-hauling-bbbb');
    });

    it('builds a usable slug when the name has no latin characters', function () {
        Actors::seedAccessControl();

        $this->post('/register', [
            'name' => '株式会社',
            'email' => 'kk@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $slug = Company::where('email', 'kk@example.com')->value('slug');
        expect($slug)->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/');
    });
});

/*
| AUTH-04 (DEFERRED): documents current behaviour. Deleting your own profile deletes the
| company and, via FK cascades, all of its drivers. Kept so a product decision changes it knowingly.
*/
describe('AUTH-04 profile deletion cascade', function () {
    it('currently deletes the company and its drivers when the owner deletes their profile', function () {
        $owner = Actors::companyOwner();
        $company = Company::where('user_id', $owner->id)->firstOrFail();
        $driver = DriverFactory::new()->create(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->delete('/admin/profile', ['password' => 'password'])
            ->assertRedirect('/');

        expect(Company::find($company->id))->toBeNull()
            ->and(Driver::find($driver->id))->toBeNull();
    });
});
