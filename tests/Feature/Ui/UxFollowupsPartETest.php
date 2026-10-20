<?php

use App\Models\Driver;
use App\Tenancy\TenantContext;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Tests\Support\Actors;

/*
| UX follow-ups part E: AppServiceProvider turns on Model::preventLazyLoading outside production,
| so an N+1 in a list throws in tests instead of passing silently.
*/

it('prevents lazy loading in the testing environment', function () {
    expect(app()->isProduction())->toBeFalse()
        ->and(Model::preventsLazyLoading())->toBeTrue();
});

it('throws when a relation is lazy loaded on a list of models', function () {
    $company = Actors::companyOf(Actors::companyOwner());
    DriverFactory::new()->forCompany($company)->count(2)->create();

    expect(fn () => app(TenantContext::class)->run($company->id, function () {
        Driver::query()->get()->each(fn (Driver $driver) => $driver->company);
    }))->toThrow(LazyLoadingViolationException::class, 'Attempted to lazy load [company] on model [App\Models\Driver]');
});

it('allows the same relation when it is eager loaded', function () {
    $company = Actors::companyOf(Actors::companyOwner());
    DriverFactory::new()->forCompany($company)->count(2)->create();

    $names = app(TenantContext::class)->run($company->id, fn () => Driver::query()->with('company')->get()->map(fn (Driver $driver) => $driver->company->name));

    expect($names->unique()->all())->toBe([$company->name]);
});
