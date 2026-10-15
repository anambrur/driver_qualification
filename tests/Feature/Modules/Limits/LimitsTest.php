<?php

use App\Exceptions\LimitReached;
use App\Features\Feature;
use App\Mail\CompanyInvitationMail;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Entitlements\Limits;
use App\Services\Entitlements\PlanFeatureService;
use App\Services\Users\InvitationService;
use App\Services\Users\UserService;
use Database\Factories\DriverFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\UserFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Modules\Entitlements\Entitled;
use Tests\Support\Actors;

/*
| 05 §5 limits (decided Q4): only active hired drivers count toward max_drivers, only active
| office users toward max_users. Every path that raises a count checks inside its transaction.
*/

function limitTo(Company $company, Feature $limit, ?int $max): void
{
    app(PlanFeatureService::class)->set(Actors::platformOwner(), Entitled::planOf($company), $limit, true, $max);
    Entitled::newRequest();
}

function limitAt(Company $company, Feature $limit): void
{
    limitTo($company, $limit, Limits::count($company->id, $limit));
}

beforeEach(function () {
    $this->owner = Actors::companyOwner();
    $this->company = Actors::companyOf($this->owner);
});

it('counts only active, hired, not deleted drivers', function () {
    $drivers = DriverFactory::new()->forCompany($this->company);
    $drivers->count(2)->create();
    $drivers->employment('terminated')->create();
    $drivers->employment('inactive')->create();
    $drivers->application('submitted')->create();
    $drivers->application('rejected')->create();
    $drivers->create()->delete();
    DriverFactory::new()->forCompany(Actors::company())->create();

    expect(Limits::count($this->company->id, Feature::MaxDrivers))->toBe(2);
});

it('counts only active office users: not driver logins, support or inactive users', function () {
    $before = Limits::count($this->company->id, Feature::MaxUsers);

    Actors::officeUser($this->company);
    Actors::driverUser($this->company);
    Actors::officeUser($this->company, ['status' => 'inactive']);
    UserFactory::new()->create(['company_id' => $this->company->id, 'user_type' => 'support']);

    expect(Limits::count($this->company->id, Feature::MaxUsers))->toBe($before + 1);
});

it('passes an unlimited limit and refuses a limit the plan does not include', function () {
    limitTo($this->company, Feature::MaxVehicles, null);
    DB::transaction(fn () => Limits::assertCanCreate($this->company, Feature::MaxVehicles, 1000));

    DB::table('plan_features')->where('plan_id', Entitled::planOf($this->company)->id)->where('feature_key', 'max_vehicles')->delete();
    $this->company->increment('entitlements_version');
    Entitled::newRequest();

    expect(fn () => DB::transaction(fn () => Limits::assertCanCreate($this->company->fresh(), Feature::MaxVehicles)))
        ->toThrow(LimitReached::class);
});

it('blocks hiring past max_drivers with a 422 and an upgrade hint', function () {
    $applicant = DriverFactory::new()->forCompany($this->company)->application('submitted')->create();
    limitAt($this->company, Feature::MaxDrivers);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('admin.driver.hire-status', $applicant->id), ['action' => 'hire', 'hire_date' => now()->toDateString(), 'hazmat' => 'no', 'lcv_certificate' => 'no'])
        ->assertStatus(422)
        ->assertJsonPath('limit.feature', 'max_drivers')
        ->assertJsonPath('upgrade_url', route('billing.index'));

    expect(DB::table('drivers')->where('id', $applicant->id)->value('employment_status'))->toBe('applicant');
});

it('hires below max_drivers into the counted state', function () {
    $applicant = DriverFactory::new()->forCompany($this->company)->application('submitted')->create();
    limitTo($this->company, Feature::MaxDrivers, Limits::count($this->company->id, Feature::MaxDrivers) + 1);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('admin.driver.hire-status', $applicant->id), ['action' => 'hire', 'hire_date' => now()->toDateString(), 'hazmat' => 'no', 'lcv_certificate' => 'no'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect(DB::table('drivers')->where('id', $applicant->id)->first())
        ->employment_status->toBe('active')
        ->application_status->toBe('approved')
        ->decided_by_user_id->toBe($this->owner->id);
});

it('hires only staff-created or submitted applicants, and rejects without hiring', function () {
    $draft = DriverFactory::new()->forCompany($this->company)->application('draft')->create();
    $submitted = DriverFactory::new()->forCompany($this->company)->application('submitted')->create();
    $owner = $this->owner->fresh();

    $this->actingAs($owner)
        ->postJson(route('admin.driver.hire-status', $draft->id), ['action' => 'hire', 'hire_date' => now()->toDateString(), 'hazmat' => 'no', 'lcv_certificate' => 'no'])
        ->assertStatus(422);
    $this->actingAs($owner)
        ->postJson(route('admin.driver.hire-status', $submitted->id), ['action' => 'reject', 'rejection_reason' => 'cdl_issues', 'record_date' => now()->toDateString()])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(DB::table('drivers')->where('id', $draft->id)->value('employment_status'))->toBe('applicant')
        ->and(DB::table('drivers')->where('id', $submitted->id)->first())
        ->application_status->toBe('rejected')
        ->employment_status->toBe('applicant')
        ->decided_by_user_id->toBe($this->owner->id);
});

it('blocks accepting an invitation past max_users', function () {
    Mail::fake();
    Actors::actingAsTenant($this->owner);
    $role = Role::query()->where('team_id', $this->company->id)->where('name', 'Dispatcher')->firstOrFail();
    app(InvitationService::class)->invite($this->owner, 'late@example.com', $role->id);
    $token = null;
    Mail::assertQueued(CompanyInvitationMail::class, function (CompanyInvitationMail $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });
    limitAt($this->company, Feature::MaxUsers);

    expect(fn () => app(InvitationService::class)->accept($token, 'Late Joiner', 'password-123'))->toThrow(LimitReached::class)
        ->and(User::query()->where('email', 'late@example.com')->exists())->toBeFalse();
});

it('blocks reactivating an office user past max_users, but not a driver login', function () {
    $office = Actors::officeUser($this->company, ['status' => 'inactive']);
    $driverLogin = Actors::driverUser($this->company, ['status' => 'inactive']);
    limitAt($this->company, Feature::MaxUsers);
    $owner = Actors::actingAsTenant($this->owner->fresh());

    expect(fn () => app(UserService::class)->setStatus($owner, $office, 'active'))->toThrow(LimitReached::class)
        ->and($office->fresh()->status)->toBe('inactive');

    app(UserService::class)->setStatus($owner, $driverLogin, 'active');

    expect($driverLogin->fresh()->status)->toBe('active');
});

it('blocks creating and restoring vehicles and trailers past their limits', function (string $asset) {
    [$feature, $factory, $store, $restore] = $asset === 'vehicle'
        ? [Feature::MaxVehicles, VehicleFactory::new(), 'admin.vehicle.store', 'admin.vehicle.restore']
        : [Feature::MaxTrailers, TrailerFactory::new(), 'admin.trailer.store', 'admin.trailer.restore'];
    $deleted = $factory->forCompany($this->company)->create();
    $deleted->delete();
    $factory->forCompany($this->company)->create();
    limitAt($this->company, $feature);
    $owner = $this->owner->fresh();

    $payload = ['unit_no' => 'U-NEW', 'vin' => '1HGBH41JXMN109186', 'year' => 2020, 'make' => 'Volvo', 'model' => 'VNL', 'odometer' => 0,
        'equipment_type_id' => DB::table('equipment_types')->insertGetId(['name' => 'Dry van', 'created_at' => now(), 'updated_at' => now()])];

    $this->actingAs($owner)->postJson(route($store), $payload)
        ->assertStatus(422)
        ->assertJsonPath('limit.feature', $feature->value);
    $this->actingAs($owner)->postJson(route($restore, $deleted->id))
        ->assertStatus(422)
        ->assertJsonPath('limit.feature', $feature->value);

    expect($deleted->fresh()->trashed())->toBeTrue();
})->with(['vehicle', 'trailer']);
