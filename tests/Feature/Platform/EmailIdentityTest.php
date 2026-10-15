<?php

use App\Mail\CompanyInvitationMail;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Platform\EmailIdentityService;
use App\Support\NamedLock;
use Database\Factories\PlatformUserFactory;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Support\Actors;

/*
| D3a (04 §2): the same email can never be both a tenant login and a platform login, from
| either side, whatever its case. Every email write goes through EmailIdentityService.
*/

beforeEach(function () {
    Notification::fake();
});

describe('a platform email can not become a tenant login', function () {
    beforeEach(function () {
        PlatformUserFactory::new()->create(['email' => 'staff@example.com']);
    });

    it('refuses it at registration', function () {
        $this->post('/register', [
            'name' => 'Someone', 'company_name' => 'Acme', 'email' => 'Staff@Example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors(['email' => EmailIdentityService::TAKEN]);

        expect(User::query()->where('email', 'staff@example.com')->exists())->toBeFalse()
            ->and($this->isAuthenticated('web'))->toBeFalse();
    });

    it('refuses it when an invitation is accepted', function () {
        $company = Actors::company();
        $roleId = (int) Role::query()->where('team_id', $company->id)->where('name', 'Dispatcher')->value('id');
        Mail::fake();
        $this->actingAs($company->owner)->post(route('users.invitations.store'), ['email' => 'staff@example.com', 'role_id' => $roleId]);
        $token = null;
        Mail::assertQueued(CompanyInvitationMail::class, function (CompanyInvitationMail $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });
        auth()->logout();

        $this->post(route('invitations.accept', $token), ['name' => 'New', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])
            ->assertSessionHasErrors('email');

        expect(User::query()->where('email', 'staff@example.com')->exists())->toBeFalse();
    });

    it('refuses it as a new profile email', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)->patch(route('profile.update'), ['name' => $owner->name, 'email' => 'staff@example.com'])
            ->assertSessionHasErrors(['email' => EmailIdentityService::TAKEN]);

        expect($owner->fresh()->email)->not->toBe('staff@example.com');
    });

    it('refuses it as the owner of a company the platform creates', function () {
        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.companies.store'), ['company_name' => 'Acme', 'owner_name' => 'Owner', 'owner_email' => 'staff@example.com'])
            ->assertSessionHasErrors(['email' => EmailIdentityService::TAKEN]);

        expect(User::query()->where('email', 'staff@example.com')->exists())->toBeFalse();
    });
});

describe('a tenant email can not become a platform login', function () {
    beforeEach(function () {
        $this->tenant = Actors::companyOwner();
        $this->owner = Actors::platformOwner();
        $this->supportRole = (int) Role::query()->where('team_id', 0)->where('system_key', 'platform_support')->value('id');
    });

    it('refuses it when staff are invited', function () {
        $this->actingAs($this->owner, 'platform')
            ->post(route('platform.staff.store'), ['name' => 'Agent', 'email' => strtoupper($this->tenant->email), 'roles' => [$this->supportRole]])
            ->assertSessionHasErrors(['email' => EmailIdentityService::TAKEN]);

        expect(PlatformUser::query()->where('email', $this->tenant->email)->exists())->toBeFalse();
    });

    it('refuses it when a staff email is changed', function () {
        $agent = Actors::platformSupport();

        $this->actingAs($this->owner, 'platform')
            ->put(route('platform.staff.update', $agent), ['name' => $agent->name, 'email' => $this->tenant->email, 'roles' => [$this->supportRole]])
            ->assertSessionHasErrors(['email' => EmailIdentityService::TAKEN]);

        expect($agent->fresh()->email)->not->toBe($this->tenant->email);
    });

    it('refuses it in the owner bootstrap command', function () {
        PlatformUser::query()->delete();

        expect(Artisan::call('platform:bootstrap-owners', ['email1' => $this->tenant->email, 'name1' => 'One', 'email2' => 'two@example.com', 'name2' => 'Two']))->toBe(1)
            ->and(PlatformUser::query()->count())->toBe(0);
    });
});

it('lets a user keep their own email and change only its case', function () {
    $owner = Actors::companyOwner();

    $this->actingAs($owner)->patch(route('profile.update'), ['name' => 'Renamed', 'email' => $owner->email])
        ->assertSessionHasNoErrors();

    expect(fn () => app(EmailIdentityService::class)->assertAvailable(strtoupper($owner->email), $owner))->not->toThrow(ValidationException::class)
        ->and(fn () => app(EmailIdentityService::class)->assertAvailable(strtoupper($owner->email)))->toThrow(ValidationException::class);
});

it('holds the email lock across the check, so a second writer waits instead of passing', function () {
    $name = 'lock:'.sha1('email:new@example.com');
    if (DB::getDriverName() === 'mysql') {
        // GET_LOCK is re-entrant per session, so the other writer holds it on its own connection
        config(['database.connections.other_writer' => config('database.connections.'.DB::getDefaultConnection())]);
        $other = DB::connection('other_writer');
        $other->scalar('SELECT GET_LOCK(?, 0)', [$name]);
        $release = fn () => $other->scalar('SELECT RELEASE_LOCK(?)', [$name]);
    } else {
        $lock = Cache::lock($name, 10);
        $lock->get();
        $release = fn () => $lock->release();
    }

    expect(fn () => NamedLock::run('email:new@example.com', 0, fn () => null))->toThrow(LockTimeoutException::class);

    $release();
    expect(NamedLock::run('email:new@example.com', 0, fn () => 'ran'))->toBe('ran');
});

it('does not report an unrelated unique violation as a taken email', function () {
    $violation = new UniqueConstraintViolationException('sqlite', 'insert into "companies" ("email", "slug") values (?, ?)', [], new PDOException('UNIQUE constraint failed: companies.slug'));

    expect(fn () => app(EmailIdentityService::class)->claim('free@example.com', fn () => throw $violation))
        ->toThrow(UniqueConstraintViolationException::class);
});
