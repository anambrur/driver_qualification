<?php

use App\Features\Feature;
use App\Models\Company;
use App\Models\Trip;
use App\Services\Operations\TripService;
use Database\Factories\TripFactory;
use Database\Factories\TripStopFactory;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| Dashboard trip counts (req 9.2, 08 §9): "On the road" = in-progress trips; "Pending pickup" =
| assigned or in-progress trips whose pickup stop (or start with also_pickup) has not departed.
| One query, only with the trips feature and trips.view; each card opens the filtered list.
*/

/**
 * A trip in $state with these stops: type => departed (the start may be the pickup).
 *
 * @param  array<string, bool>  $stops
 */
function countedTrip(Company $company, string $state, array $stops = [], bool $alsoPickup = false): Trip
{
    $trip = TripFactory::new()->forCompany($company)->{$state}()->create();
    $sequence = 0;

    foreach ($stops as $type => $departed) {
        TripStopFactory::new()->onTrip($trip, $type, ++$sequence)->create([
            'also_pickup' => $type === 'start' && $alsoPickup,
            'departed_at' => $departed ? now()->subHour() : null,
        ]);
    }

    return $trip;
}

/**
 * One trip of every kind; returns the ids that count as pending pickup.
 *
 * @return array{on_road: list<int>, pending: list<int>}
 */
function seedTripStates(Company $company): array
{
    $waiting = ['start' => false, 'pickup' => false, 'drop_off' => false];

    countedTrip($company, 'draft', $waiting);
    countedTrip($company, 'planned', $waiting);
    $assigned = countedTrip($company, 'assigned', $waiting);
    countedTrip($company, 'assigned', ['start' => false, 'drop_off' => false]);
    $assignedStart = countedTrip($company, 'assigned', ['start' => false, 'drop_off' => false], alsoPickup: true);
    $pickedUp = countedTrip($company, 'inProgress', ['start' => true, 'pickup' => true, 'drop_off' => false]);
    $notPickedUp = countedTrip($company, 'inProgress', ['start' => true, 'pickup' => false, 'drop_off' => false]);
    $startPickedUp = countedTrip($company, 'inProgress', ['start' => true, 'drop_off' => false], alsoPickup: true);
    $nothingToPick = countedTrip($company, 'inProgress', ['start' => true, 'drop_off' => false]);
    countedTrip($company, 'completed', $waiting);
    countedTrip($company, 'cancelled', $waiting);
    countedTrip($company, 'cancelled', $waiting)->delete();
    countedTrip($company, 'assigned', $waiting)->delete();

    return [
        'on_road' => [$pickedUp->id, $notPickedUp->id, $startPickedUp->id, $nothingToPick->id],
        'pending' => [$assigned->id, $assignedStart->id, $notPickedUp->id],
    ];
}

function pendingPickupRows($test): array
{
    return $test->getJson(route('trips.data').'?'.http_build_query([
        'pickup' => 'pending', 'draw' => 1, 'start' => 0, 'length' => 50, 'order' => [['column' => 1, 'dir' => 'desc']],
        'columns' => [['data' => 'number', 'name' => 'number'], ['data' => 'date', 'name' => 'date']],
    ]))->assertOk()->json('data');
}

it('counts the loads on the road and pending pickup by trip state', function () {
    $company = Actors::company();
    $ids = seedTripStates($company);
    countedTrip(Actors::company(), 'inProgress', ['start' => false, 'pickup' => false]);

    expect(Trips::service($company, fn (TripService $trips) => $trips->roadCounts()))
        ->toBe(['on_road' => count($ids['on_road']), 'pending_pickup' => count($ids['pending'])]);

    $this->actingAs($company->owner)->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('tripStats', ['on_road' => 4, 'pending_pickup' => 3])
        ->assertSee('On the road')->assertSee('Pending pickup')
        ->assertSee(route('trips.index', ['status' => 'in_progress']))
        ->assertSee(route('trips.index', ['pickup' => 'pending']));
});

it('lists the same trips as the pending pickup card', function () {
    $company = Actors::company();
    $ids = seedTripStates($company);
    countedTrip(Actors::company(), 'inProgress', ['start' => false, 'pickup' => false]);
    $this->actingAs($company->owner);

    $listed = collect(pendingPickupRows($this))
        ->map(fn (array $row) => preg_match('/#(\d+)/', $row['number'], $m) === 1 ? (int) $m[1] : null)->sort()->values()->all();
    $expected = Trip::withoutTenancy()->whereIn('id', $ids['pending'])->pluck('trip_number')->sort()->values()->all();

    expect($listed)->toBe($expected);
});

it('opens the trips list filtered by the card', function () {
    $company = Actors::company();
    $this->actingAs($company->owner);

    $this->get(route('trips.index', ['status' => 'in_progress']))->assertOk()
        ->assertSee('<option value="in_progress" selected', false);
    $this->get(route('trips.index', ['pickup' => 'pending']))->assertOk()
        ->assertSee('aria-label="Remove the filter Pending pickup"', false);
    $this->get(route('trips.index', ['status' => 'bogus']))->assertOk()
        ->assertDontSee('<option value="bogus"', false);
});

it('hides the cards and runs no trip query without the trips feature', function () {
    $company = Actors::withPlan(Actors::company(), collect(Feature::cases())
        ->reject(fn (Feature $f) => in_array($f, [Feature::Trips, Feature::PreTripInspections, Feature::TripEvents], true) || $f->isLimit())->map->value->all());

    $this->actingAs($company->owner)->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('tripStats', null)
        ->assertDontSee('Pending pickup');
});

it('hides the cards from a user without trips.view', function () {
    $company = Actors::company();

    $this->actingAs(Actors::officeUser($company))->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('tripStats', null)
        ->assertDontSee('Pending pickup');
    $this->actingAs(Actors::dispatcher($company))->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('tripStats', ['on_road' => 0, 'pending_pickup' => 0]);
});

it('keeps the dashboard and the filtered list at a constant query count', function () {
    $company = Actors::company();
    $this->actingAs($company->owner);
    $seed = function (int $n) use ($company) {
        for ($i = 0; $i < $n; $i++) {
            countedTrip($company, 'inProgress', ['start' => true, 'pickup' => false, 'drop_off' => false]);
            countedTrip($company, 'assigned', ['start' => false, 'drop_off' => false], alsoPickup: true);
        }
    };

    Perf::assertConstantQueries($seed, fn () => $this->get(route('admin.dashboard'))->assertOk());
    Perf::assertConstantQueries($seed, fn () => pendingPickupRows($this));
});
