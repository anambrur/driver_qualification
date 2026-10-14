<?php

use App\Models\Company;
use App\Models\PlatformUser;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
| migrate:fresh --seed produces a usable demo (11 §6, seed smoke check). Interim (WP1): the
| full system-role set, the support user and the trip sequence arrive with WP2, WP3 and Phase 2.
*/

beforeEach(function () {
    Http::fake(['*' => Http::response([], 500)]);
    $this->seed(DatabaseSeeder::class);
});

it('gives each demo company an owner with the Owner role, settings and an open subscription', function () {
    $companies = Company::with('owner', 'settings')->get();

    expect($companies)->toHaveCount(2);

    foreach ($companies as $company) {
        $ownerRole = DB::table('roles')->where('team_id', $company->id)->where('system_key', 'owner')->value('id');

        expect($company->owner->company_id)->toBe($company->id)
            ->and($company->settings)->not->toBeNull()
            ->and($company->subscriptions()->whereNull('ended_at')->count())->toBe(1)
            ->and(DB::table('model_has_roles')->where(['team_id' => $company->id, 'role_id' => $ownerRole, 'model_id' => $company->owner_user_id])->exists())->toBeTrue()
            ->and(app(TenantContext::class)->run($company->id, fn () => $company->drivers()->whereHas('currentLicense')->count()))->toBe(3);
    }
});

it('has at least two active Platform Owners', function () {
    $owners = DB::table('platform_users as p')
        ->join('model_has_roles as m', fn ($j) => $j->on('m.model_id', '=', 'p.id')->where('m.model_type', PlatformUser::class))
        ->join('roles as r', fn ($j) => $j->on('r.id', '=', 'm.role_id')->on('r.team_id', '=', 'm.team_id'))
        ->where('m.team_id', 0)
        ->where('r.system_key', 'platform_owner')
        ->where('p.status', 'active')
        ->count();

    expect($owners)->toBeGreaterThanOrEqual(2);
});

it('seeds the medical examiner certificate as a system document type that requires expiry', function () {
    expect(DB::table('document_types')->where('system_key', 'medical_examiner_certificate')->value('requires_expiry'))->toBeTruthy();
});

it('can be seeded twice without errors', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Company::count())->toBe(2)->and(PlatformUser::count())->toBe(2);
});

it('gives each company all seven system roles')->todo('WP3 role templates via CompanyProvisioner');
it('gives each company a support user')->todo('WP2 CompanyProvisioner');
it('gives each company a trip number sequence')->todo('Phase 2 trip_number_sequences');
