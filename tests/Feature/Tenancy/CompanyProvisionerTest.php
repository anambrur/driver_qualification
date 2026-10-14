<?php

use App\Authorization\RoleTemplates;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Company\CompanyProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

function provisionCompany(string $name = 'Acme Trucking', string $email = 'owner@acme.test'): Company
{
    return app(CompanyProvisioner::class)->provision(
        ['company_name' => $name, 'email' => $email],
        ['name' => 'Jane Owner', 'email' => $email, 'password' => 'secret-password'],
    );
}

it('creates the company with its owner, settings and trip sequence', function () {
    $company = provisionCompany()->fresh();

    expect($company->status)->toBe('active')
        ->and($company->slug)->toStartWith('acme-trucking-')
        ->and($company->owner->company_id)->toBe($company->id)
        ->and($company->owner->user_type)->toBe('member')
        ->and($company->owner->email)->toBe('owner@acme.test')
        ->and($company->settings)->not->toBeNull()
        ->and(DB::table('trip_number_sequences')->where('company_id', $company->id)->value('next_number'))->toBe(1);
});

it('keeps the owner status the caller gives, and the owner is always a member', function () {
    $company = app(CompanyProvisioner::class)->provision(
        ['company_name' => 'Paused Co', 'status' => 'deactivated'],
        ['name' => 'Paused Owner', 'email' => 'paused@test.test', 'password' => 'secret-password', 'status' => 'inactive', 'user_type' => 'support'],
    );

    expect($company->fresh()->status)->toBe('deactivated')
        ->and($company->owner->fresh()->status)->toBe('inactive')
        ->and($company->owner->fresh()->user_type)->toBe('member');
});

it('creates the seven system roles in the company team and gives the owner Owner', function () {
    $company = provisionCompany();

    $roles = DB::table('roles')->where('team_id', $company->id)->orderBy('id')->get();

    expect($roles->pluck('system_key', 'name')->all())->toBe(RoleTemplates::SYSTEM_ROLES)
        ->and($roles)->toHaveCount(7)
        ->and($roles->every(fn ($role) => $role->guard_name === 'web' && (bool) $role->is_system))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
    expect($company->owner->fresh()->getRoleNames()->all())->toBe(['Owner']);
});

it('creates one inactive support user without a role', function () {
    $company = provisionCompany();

    $support = User::where('company_id', $company->id)->where('user_type', 'support')->sole();

    expect($support->status)->toBe('inactive')
        ->and($support->email)->toMatch("/^support-{$company->id}-[a-z0-9]{12}@support\\.invalid$/")
        ->and(DB::table('model_has_roles')->where('model_id', $support->id)->where('model_type', User::class)->exists())->toBeFalse()
        ->and(User::where('company_id', $company->id)->count())->toBe(2);
});

it('gives each company its own roles', function () {
    $a = provisionCompany('Alpha', 'a@alpha.test');
    $b = provisionCompany('Beta', 'b@beta.test');

    $idsA = DB::table('roles')->where('team_id', $a->id)->pluck('id');
    $idsB = DB::table('roles')->where('team_id', $b->id)->pluck('id');

    expect($idsA)->toHaveCount(7)
        ->and($idsB)->toHaveCount(7)
        ->and($idsA->intersect($idsB))->toBeEmpty();
});

it('rolls everything back when a step fails', function () {
    provisionCompany('Existing', 'x@existing.test');
    // Fails after the company, owner, settings and sequence rows are written.
    Role::creating(fn () => throw new RuntimeException('role store unavailable'));
    $before = [Company::count(), User::count(), DB::table('roles')->count(), DB::table('company_settings')->count(), DB::table('trip_number_sequences')->count()];

    expect(fn () => provisionCompany('Doomed', 'd@doomed.test'))->toThrow(RuntimeException::class, 'role store unavailable');

    expect([Company::count(), User::count(), DB::table('roles')->count(), DB::table('company_settings')->count(), DB::table('trip_number_sequences')->count()])->toBe($before)
        ->and(Company::where('company_name', 'Doomed')->exists())->toBeFalse();
});

it('provisions a company on registration with the given company name', function () {
    $this->post('/register', [
        'name' => 'Jane Owner',
        'company_name' => 'Jane Freight LLC',
        'email' => 'Jane@Freight.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $company = Company::where('company_name', 'Jane Freight LLC')->sole();

    $this->assertAuthenticatedAs($company->owner);
    expect($company->owner->name)->toBe('Jane Owner')
        ->and($company->owner->email)->toBe('jane@freight.test')
        ->and(DB::table('roles')->where('team_id', $company->id)->count())->toBe(7);
});

it('requires a company name and a free email on registration', function () {
    provisionCompany('Taken', 'taken@test.test');

    $this->post('/register', [
        'name' => 'Someone',
        'email' => 'taken@test.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['company_name', 'email']);

    $this->assertGuest();
});

it('creates companies only through the provisioner', function () {
    $offenders = collect(File::allFiles([app_path(), database_path()]))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->reject(fn ($file) => $file->getRealPath() === realpath(app_path('Services/Company/CompanyProvisioner.php')))
        ->filter(fn ($file) => preg_match(
            '/Company::(query\(\)->)?(create|forceCreate|firstOrCreate|updateOrCreate|insert)\b|new Company\b|table\([\'"]companies[\'"]\)->insert/',
            $file->getContents()
        ))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
