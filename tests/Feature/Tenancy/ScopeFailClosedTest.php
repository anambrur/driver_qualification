<?php

use App\Tenancy\CrossTenantWriteException;
use App\Tenancy\MissingTenantContextException;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRule;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\Fixtures\ScopedDriver;
use Tests\Support\Actors;

beforeEach(function () {
    $this->companyA = Actors::company();
    $this->companyB = Actors::company();
    $this->driverA = DriverFactory::new()->forCompany($this->companyA)->create();
    $this->driverB = DriverFactory::new()->forCompany($this->companyB)->create();
    $this->tenant = app(TenantContext::class);
});

it('throws when a tenant model is queried without a context', function () {
    expect(fn () => ScopedDriver::query()->get())->toThrow(MissingTenantContextException::class)
        ->and(fn () => ScopedDriver::find($this->driverA->id))->toThrow(MissingTenantContextException::class)
        ->and(fn () => ScopedDriver::count())->toThrow(MissingTenantContextException::class);
});

it('throws in platform mode', function () {
    $this->tenant->setPlatform();

    expect(fn () => ScopedDriver::query()->get())->toThrow(MissingTenantContextException::class);
});

it('returns only the current company rows with a context', function () {
    $ids = $this->tenant->run($this->companyA->id, fn () => ScopedDriver::pluck('id')->all());

    expect($ids)->toBe([$this->driverA->id]);
});

it('does not find another company row by id', function () {
    $found = $this->tenant->run($this->companyA->id, fn () => ScopedDriver::find($this->driverB->id));

    expect($found)->toBeNull();
});

it('returns every company with withoutTenancy', function () {
    expect(ScopedDriver::withoutTenancy()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->driverA->id, $this->driverB->id])->sort()->values()->all());
});

it('fills company_id on create and refuses another company', function () {
    $attributes = fn () => collect($this->driverA->getAttributes())
        ->except(['id', 'company_id', 'email', 'main_phone'])
        ->merge(['email' => fake()->unique()->safeEmail(), 'main_phone' => '+12025550199'])
        ->all();

    $created = $this->tenant->run($this->companyA->id, fn () => ScopedDriver::create($attributes()));

    expect($created->company_id)->toBe($this->companyA->id)
        ->and(fn () => $this->tenant->run($this->companyA->id, fn () => ScopedDriver::create(['company_id' => $this->companyB->id] + $attributes())))
        ->toThrow(CrossTenantWriteException::class)
        ->and(fn () => ScopedDriver::create($attributes()))
        ->toThrow(MissingTenantContextException::class);
});

it('binds route models inside the tenant context, so a foreign id is a 404', function () {
    Route::middleware(['web', 'auth'])->get('/_tenancy-test/{scopedDriver}', fn (ScopedDriver $scopedDriver) => (string) $scopedDriver->id);

    $this->actingAs($this->companyA->owner)
        ->get("/_tenancy-test/{$this->driverA->id}")
        ->assertOk()
        ->assertSeeText((string) $this->driverA->id);

    $this->actingAs($this->companyA->owner)
        ->get("/_tenancy-test/{$this->driverB->id}")
        ->assertNotFound();
});

it('restores the previous context after the request', function () {
    Route::middleware(['web', 'auth'])->get('/_tenancy-test-ctx', fn () => (string) app(TenantContext::class)->companyId());

    $this->actingAs($this->companyA->owner)->get('/_tenancy-test-ctx')->assertSeeText((string) $this->companyA->id);

    expect($this->tenant->companyId())->toBeNull();
});

it('logs out every user of a suspended company', function () {
    $admin = Actors::companyAdmin($this->companyA);
    Actors::suspended($this->companyA);
    Route::middleware(['web', 'auth'])->get('/_tenancy-test-ctx', fn () => 'ok');

    $this->actingAs($admin)->get('/_tenancy-test-ctx')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('limits TenantRule exists and unique to the current company', function () {
    $passes = fn (Closure $rules, array $data) => $this->tenant->run(
        $this->companyA->id,
        fn () => Validator::make($data, $rules())->passes()
    );
    $exists = fn () => ['id' => [TenantRule::exists('drivers')]];
    $unique = fn () => ['email' => [TenantRule::unique('drivers', 'email')]];

    expect($passes($exists, ['id' => $this->driverA->id]))->toBeTrue()
        ->and($passes($exists, ['id' => $this->driverB->id]))->toBeFalse()
        ->and($passes($unique, ['email' => $this->driverB->email]))->toBeTrue()
        ->and($passes($unique, ['email' => $this->driverA->email]))->toBeFalse()
        ->and(fn () => TenantRule::exists('drivers'))->toThrow(MissingTenantContextException::class);
});
