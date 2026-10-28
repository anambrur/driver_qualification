<?php

use App\Features\Feature;
use App\Models\Company;
use App\Models\Trip;
use App\Models\TripStop;
use App\Support\Navigation\CompanyMenu;
use Database\Factories\TripFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;

/*
| Who reaches the trip pages (req 4.1–4.3): trips.view reads; trips.create, trips.edit,
| trips.confirm-number and trips.delete write; the trips feature gates all of them and a
| read-only company can't write (Q2). Another company's trip is a 404 (IsolationMatrixTest).
*/

beforeEach(function () {
    Shippers::seedStates();
});

/**
 * Every trips write route with its method, URL, payload and permission.
 *
 * @return list<array{0: string, 1: string, 2: array<string, mixed>, 3: string}>
 */
function tripWriteRoutes(Trip $trip): array
{
    return [
        ['GET', route('trips.create'), [], 'trips.create'],
        ['POST', route('trips.store'), Trips::details(), 'trips.create'],
        ['PUT', route('trips.update', $trip), Trips::details(), 'trips.edit'],
        ['PUT', route('trips.stops.update', $trip), Trips::stopsPayload(), 'trips.edit'],
        ['POST', route('trips.confirm-number', $trip), [], 'trips.confirm-number'],
        ['POST', route('trips.cancel', $trip), ['cancel_reason' => 'No load'], 'trips.edit'],
        ['DELETE', route('trips.destroy', $trip), [], 'trips.delete'],
        // P2-3: assignment and the lifecycle
        ['PUT', route('trips.assignment.update', $trip), ['driver_id' => 1], 'trips.assign'],
        ['DELETE', route('trips.assignment.destroy', $trip), [], 'trips.assign'],
        ['POST', route('trips.equipment.confirm', $trip), [], 'trips.assign'],
        ['POST', route('trips.start', $trip), [], 'trips.edit'],
        ['POST', route('trips.picked-up', $trip), [], 'trips.edit'],
        ['PUT', route('trips.stop-times.update', $trip), ['stops' => []], 'trips.edit'],
        ['POST', route('trips.complete', $trip), [], 'trips.edit'],
    ];
}

/**
 * Each P2-3 route on a trip in the state it needs, with a payload that succeeds.
 *
 * @return array<string, array{0: Closure(Company): array{0: string, 1: string, 2: array<string, mixed>}, 1: string}>
 */
function tripProgressRoutes(): array
{
    return [
        'assign' => [function (Company $company) {
            $trip = Trips::planned($company);
            $crew = Trips::crew($company);

            return ['PUT', route('trips.assignment.update', $trip), ['driver_id' => $crew['driver']->id, 'vehicle_id' => $crew['vehicle']->id]];
        }, 'trips.assign'],
        'unassign' => [fn (Company $company) => ['DELETE', route('trips.assignment.destroy', Trips::assigned($company)), []], 'trips.assign'],
        'confirm equipment' => [fn (Company $company) => ['POST', route('trips.equipment.confirm', Trips::assigned($company, confirmed: false)), []], 'trips.assign'],
        'start' => [fn (Company $company) => ['POST', route('trips.start', Trips::inspected(Trips::assigned($company))), []], 'trips.edit'],
        'picked up' => [fn (Company $company) => ['POST', route('trips.picked-up', Trips::inProgress($company, alsoPickup: true)), []], 'trips.edit'],
        'stop times' => [function (Company $company) {
            $trip = Trips::inProgress($company);
            $stop = TripStop::withoutTenancy()->where('trip_id', $trip->id)->value('id');

            return ['PUT', route('trips.stop-times.update', $trip), ['stops' => [$stop => ['arrived_at' => null, 'departed_at' => null]]]];
        }, 'trips.edit'],
        'complete' => [fn (Company $company) => ['POST', route('trips.complete', Trips::inProgress($company)), []], 'trips.edit'],
    ];
}

it('lets trips.view read the list and the trip but not write', function () {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->create();
    $this->actingAs(Actors::memberWithPermissions($company, ['trips.view']));

    $this->get(route('trips.index'))->assertOk()->assertDontSee(route('trips.create'));
    $this->getJson(route('trips.data').'?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 1);
    $this->get(route('trips.show', $trip))->assertOk()
        ->assertDontSee('Save details')->assertDontSee('Save stops')->assertDontSee('Confirm number and date')
        ->assertDontSee(route('trips.cancel', $trip))->assertDontSee('value="DELETE"', false);

    foreach (tripWriteRoutes($trip) as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertForbidden();
    }
});

it('opens each write route with its own permission only', function (int $index) {
    $company = Actors::company();
    $trip = Trips::create($company);
    [$method, $url, $payload, $permission] = tripWriteRoutes($trip)[$index];
    $others = collect(['trips.create', 'trips.edit', 'trips.confirm-number', 'trips.delete'])->reject(fn ($p) => $p === $permission)->all();

    $this->actingAs(Actors::memberWithPermissions($company, ['trips.view', ...$others]))->json($method, $url, $payload)->assertForbidden();
    $response = $this->actingAs(Actors::memberWithPermissions($company, ['trips.view', $permission]))->json($method, $url, $payload);
    expect($response->status())->toBeIn([200, 302])->and(session('errors'))->toBeNull();
})->with([0, 1, 2, 3, 4, 5, 6]);

it('opens each assignment and lifecycle route with its own permission only', function (Closure $route, string $permission) {
    $company = Actors::company();
    [$method, $url, $payload] = $route($company);
    $others = collect(['trips.create', 'trips.edit', 'trips.assign', 'trips.confirm-number', 'trips.delete'])->reject(fn ($p) => $p === $permission)->all();

    $this->actingAs(Actors::memberWithPermissions($company, ['trips.view', ...$others]))->json($method, $url, $payload)->assertForbidden();
    $response = $this->actingAs(Actors::memberWithPermissions($company, ['trips.view', $permission]))->json($method, $url, $payload);
    expect($response->status())->toBeIn([200, 302])->and(session('errors'))->toBeNull();
})->with(tripProgressRoutes());

it('refuses the assignment and lifecycle routes for a read-only company', function (Closure $route) {
    $company = Actors::readOnly(Actors::company());
    [$method, $url, $payload] = $route($company);

    $this->actingAs($company->owner)->json($method, $url, $payload)->assertForbidden();
})->with(tripProgressRoutes());

it('refuses a member without trip permissions', function () {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->create();
    $this->actingAs(Actors::plainUser($company));

    $this->get(route('trips.index'))->assertForbidden();
    $this->getJson(route('trips.data'))->assertForbidden();
    $this->get(route('trips.show', $trip))->assertForbidden();
    foreach (tripWriteRoutes($trip) as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertForbidden();
    }
});

it('gives the Dispatcher the whole flow', function () {
    $company = Actors::company();
    $this->actingAs(Actors::dispatcher($company));

    $this->get(route('trips.index'))->assertOk()->assertSee(route('trips.create'));
    $this->get(route('trips.create'))->assertOk();
    $this->post(route('trips.store'), Trips::details())->assertRedirect();
    $trip = Trip::withoutTenancy()->where('company_id', $company->id)->sole();

    $this->get(route('trips.show', $trip))->assertOk()->assertSee('Confirm number and date')->assertSee('Save stops');
    $this->put(route('trips.stops.update', $trip), Trips::stopsPayload())->assertSessionHasNoErrors();
    $this->post(route('trips.confirm-number', $trip))->assertSessionHasNoErrors();

    expect($trip->fresh()->status)->toBe('planned');

    $crew = Trips::crew($company);
    $this->put(route('trips.assignment.update', $trip), ['driver_id' => $crew['driver']->id, 'vehicle_id' => $crew['vehicle']->id])->assertSessionHasNoErrors();
    $this->get(route('trips.show', $trip))->assertOk()->assertSee('Confirm equipment')->assertSee('Unassign');
    $this->post(route('trips.equipment.confirm', $trip))->assertSessionHasNoErrors();
    Trips::inspected($trip->fresh());
    $this->post(route('trips.start', $trip))->assertSessionHasNoErrors();
    $this->get(route('trips.show', $trip))->assertOk()->assertSee('Save stop times')->assertSee('Complete trip');
    $this->post(route('trips.complete', $trip), ['actual_distance_miles' => '240'])->assertSessionHasNoErrors();

    expect($trip->fresh()->status)->toBe('completed');
});

it('refuses every write for a read-only company but keeps the pages', function () {
    $company = Actors::readOnly(Actors::company());
    $trip = TripFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    $this->get(route('trips.index'))->assertOk();
    $this->get(route('trips.show', $trip))->assertOk();
    foreach (tripWriteRoutes($trip) as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertForbidden();
    }
    expect($trip->fresh()->trashed())->toBeFalse();
});

it('hides the menu and refuses the routes without the trips feature', function () {
    $company = Actors::company();
    Actors::withPlan($company, collect(Feature::cases())->reject(fn (Feature $f) => $f === Feature::Trips || $f->isLimit())->map->value->all());
    $trip = TripFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    $this->get(route('trips.index'))->assertForbidden();
    $this->getJson(route('trips.data'))->assertForbidden();
    $this->get(route('trips.show', $trip))->assertForbidden();
    foreach (tripWriteRoutes($trip) as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertForbidden();
    }

    $owner = Actors::actingAsTenant($company->owner);
    $labels = collect(CompanyMenu::for($owner, new Request))->flatMap(fn ($group) => collect($group['items'])->pluck('label'));
    expect($labels)->not->toContain('Trips');
});

it('shows the Trips menu item to staff with trips.view', function () {
    $owner = Actors::actingAsTenant(Actors::companyOwner());

    $operations = collect(CompanyMenu::for($owner, new Request))->firstWhere('label', 'Operations');
    expect(collect($operations['items'])->pluck('label'))->toContain('Trips');
});

it('hides another company\'s trip', function () {
    $trip = TripFactory::new()->forCompany(Actors::company())->create();

    $this->actingAs(Actors::companyOwner())->get(route('trips.show', $trip))->assertNotFound();
});

it('lets a driver login see only the trips of its own driver record', function () {
    $company = Actors::company();
    $driverUser = Actors::driverUser($company);
    $own = TripFactory::new()->forCompany($company)->assigned()->create(['driver_id' => Actors::driverOf($driverUser)->id]);
    $other = TripFactory::new()->forCompany($company)->assigned()->create();

    Actors::actingAsTenant($driverUser);
    expect(Gate::forUser($driverUser)->allows('view', $own))->toBeTrue()
        ->and(Gate::forUser($driverUser)->allows('view', $other))->toBeFalse();
    // The staff pages and actions need staff permissions (the app comes in Phase 3)
    $this->actingAs($driverUser)->get(route('trips.show', $own))->assertForbidden();
    foreach (array_slice(tripWriteRoutes($own), 7) as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertForbidden();
    }
});
