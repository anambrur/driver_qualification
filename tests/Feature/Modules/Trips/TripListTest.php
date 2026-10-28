<?php

use App\Models\Company;
use App\Models\Trip;
use Database\Factories\ShipperFactory;
use Database\Factories\TripFactory;
use Database\Factories\TripStopFactory;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| The trips list (req 4.1, yajra): number, date, status, shipper and route, newest date first;
| status, date, driver and vehicle filters; search by number, product or shipper; a constant
| query count. The trip page stays within a query budget with all its tabs and cards, and the
| driver and vehicle pages show their recent trips.
*/

beforeEach(function () {
    Shippers::seedStates();
});

function tripRows($test, array $query = []): array
{
    return $test->getJson(route('trips.data').'?'.http_build_query($query + [
        'draw' => 1, 'start' => 0, 'length' => 50, 'order' => [['column' => 1, 'dir' => 'desc']],
        'columns' => [['data' => 'number', 'name' => 'number'], ['data' => 'date', 'name' => 'date']],
    ]))->assertOk()->json('data');
}

function routedTrip(Company $company, array $attributes = []): Trip
{
    $trip = TripFactory::new()->forCompany($company)->create($attributes);
    TripStopFactory::new()->onTrip($trip, 'start', 1)->create(['city' => 'Dallas', 'state_code' => 'TX']);
    TripStopFactory::new()->onTrip($trip, 'drop_off', 2)->create(['city' => 'Houston', 'state_code' => 'TX']);

    return $trip;
}

it('lists the trips with their shipper and route, newest date first', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create(['name' => 'Acme Foods']);
    routedTrip($company, ['trip_number' => 7, 'trip_date' => '2026-10-20', 'shipper_id' => $shipper->id]);
    TripFactory::new()->forCompany($company)->cancelled()->create(['trip_number' => 8, 'trip_date' => '2026-10-25']);
    TripFactory::new()->forCompany(Actors::company())->create();
    $shipper->delete();
    $this->actingAs($company->owner);

    $rows = tripRows($this);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['number'])->toContain('#8')
        ->and($rows[0]['status'])->toContain('Cancelled')
        ->and($rows[0]['route'])->toBe('—')
        ->and($rows[1]['number'])->toContain('#7')
        ->and($rows[1]['date'])->toBe('Oct 20, 2026')
        ->and($rows[1]['shipper'])->toBe('Acme Foods')
        ->and($rows[1]['route'])->toBe('Dallas, TX → Houston, TX')
        ->and($rows[1]['driver'])->toBe('—')
        ->and($rows[1]['vehicle'])->toBe('—');
});

it('filters by status and trip date, and searches number, product and shipper', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create(['name' => 'Gulf Produce']);
    TripFactory::new()->forCompany($company)->create(['trip_number' => 11, 'trip_date' => '2026-10-01', 'product' => 'Lumber']);
    TripFactory::new()->forCompany($company)->planned()->create(['trip_number' => 12, 'trip_date' => '2026-10-15', 'product' => 'Steel', 'shipper_id' => $shipper->id]);
    TripFactory::new()->forCompany($company)->cancelled()->create(['trip_number' => 13, 'trip_date' => '2026-10-30', 'product' => 'Steel']);
    $this->actingAs($company->owner);
    $numbers = fn (array $query) => collect(tripRows($this, $query))->map(fn ($row) => (int) preg_replace('/\D/', '', strip_tags(explode('</a>', $row['number'])[0])))->sort()->values()->all();

    expect($numbers(['status' => 'planned']))->toBe([12])
        ->and($numbers(['status' => 'bogus']))->toBe([11, 12, 13])
        ->and($numbers(['date_from' => '2026-10-10']))->toBe([12, 13])
        ->and($numbers(['date_from' => '2026-10-10', 'date_to' => '2026-10-20']))->toBe([12])
        ->and($numbers(['search' => ['value' => '#13']]))->toBe([13])
        ->and($numbers(['search' => ['value' => 'steel']]))->toBe([12, 13])
        ->and($numbers(['search' => ['value' => 'gulf']]))->toBe([12])
        // Impossible dates are ignored, never a 500
        ->and($numbers(['date_from' => '2026-13-01', 'date_to' => '2026-02-31']))->toBe([11, 12, 13]);
});

it('keeps the list query count flat as trips grow', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    Perf::assertConstantQueries(
        fn (int $n) => collect(range(1, $n))->each(fn () => routedTrip($company, ['shipper_id' => $shipper->id])),
        fn () => tripRows($this),
        small: 2, large: 25,
    );
});

it('opens the trip page within its query budget', function () {
    $company = Actors::company();
    $trip = Trips::create($company, ['shipper_id' => ShipperFactory::new()->forCompany($company)->create()->id]);
    Trips::service($company, fn ($trips) => $trips->saveStops($trip, Trips::route(pickup: true), $company->owner));
    ShipperFactory::new()->forCompany($company)->count(5)->create();
    $this->actingAs($company->owner);
    $this->get(route('trips.show', $trip))->assertOk(); // warm caches as a second visit would

    Perf::assertMaxQueries(10, fn () => $this->get(route('trips.show', $trip))->assertOk()
        ->assertSee('Trip documents')->assertSee('Fort Worth')->assertSee('data-trip-map', false));
});

it('shows a read-only trip page for a completed trip', function () {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->completed()->create(['product' => 'Lumber']);

    $this->actingAs($company->owner)->get(route('trips.show', $trip))->assertOk()
        ->assertSee('Lumber')->assertDontSee('Save details')->assertDontSee('Save stops');
});

it('filters by driver and vehicle, as the driver and vehicle pages link', function () {
    $company = Actors::company();
    $mine = TripFactory::new()->forCompany($company)->assigned()->create(['trip_number' => 21]);
    TripFactory::new()->forCompany($company)->assigned()->create(['trip_number' => 22]);
    $this->actingAs($company->owner);
    $numbers = fn (array $query) => collect(tripRows($this, $query))->map(fn ($row) => (int) preg_replace('/\D/', '', strip_tags(explode('</a>', $row['number'])[0])))->sort()->values()->all();

    expect($numbers(['driver_id' => $mine->driver_id]))->toBe([21])
        ->and($numbers(['vehicle_id' => $mine->vehicle_id]))->toBe([21])
        ->and($numbers(['driver_id' => 'x']))->toBe([21, 22]);

    $this->get(route('trips.index', ['driver_id' => $mine->driver_id]))->assertOk()
        ->assertSee('d["driver_id"] = '.$mine->driver_id.';', false)->assertSee('Driver: ');
});

it('opens an assigned trip with the pickers and warnings within its query budget', function () {
    $company = Actors::company();
    $trip = Trips::assigned($company, confirmed: false, pickup: true);
    Trips::assigned($company, confirmed: false);
    collect(range(1, 3))->each(fn () => Trips::crew($company));
    $this->actingAs(Actors::dispatcher($company));
    $this->get(route('trips.show', $trip))->assertOk();

    // 16 before P2-4, + the Inspection tab and the pre-trip gate's latest inspection
    Perf::assertMaxQueries(18, fn () => $this->get(route('trips.show', $trip))->assertOk()
        ->assertSee('Assignment')->assertSee('Confirm equipment')->assertSee('data-group-defaults', false));
});

it('opens a trip in progress within its query budget', function () {
    $company = Actors::company();
    $trip = Trips::inProgress($company, pickup: true);
    $this->actingAs(Actors::dispatcher($company));
    $this->get(route('trips.show', $trip))->assertOk();

    // 7 before P2-4, + the Inspection tab: inspections, their templates, the failed results
    Perf::assertMaxQueries(10, fn () => $this->get(route('trips.show', $trip))->assertOk()->assertSee('Save stop times'));
});

it('shows the recent trips on the driver and vehicle pages to staff with trips.view', function () {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->assigned()->create(['trip_number' => 31]);
    TripFactory::new()->forCompany($company)->count(11)->create();
    $this->actingAs($company->owner);

    $this->get(route('admin.driver.show', $trip->driver_id))->assertOk()
        ->assertSee('Recent trips')->assertSee('#31')->assertSee(route('trips.index', ['driver_id' => $trip->driver_id]));
    $this->get(route('admin.vehicle.show', $trip->vehicle_id))->assertOk()
        ->assertSee('Recent trips')->assertSee('#31')->assertSee(route('trips.index', ['vehicle_id' => $trip->vehicle_id]));

    $office = Actors::officeUser($company);
    $this->actingAs($office)->get(route('admin.driver.show', $trip->driver_id))->assertOk()->assertDontSee('Recent trips');
    $this->actingAs($office)->get(route('admin.vehicle.show', $trip->vehicle_id))->assertOk()->assertDontSee('Recent trips');
});

it('keeps the recent trips to one query and ten rows', function () {
    $company = Actors::company();
    $driver = TripFactory::new()->forCompany($company)->assigned()->create()->driver_id;
    $this->actingAs($company->owner);

    $count = fn () => Perf::countQueries(fn () => $this->get(route('admin.driver.show', $driver))->assertOk());
    $this->get(route('admin.driver.show', $driver));
    $before = $count();
    TripFactory::new()->forCompany($company)->count(12)->assigned()->create(['driver_id' => $driver]);

    expect($count())->toBe($before)
        ->and(substr_count($this->get(route('admin.driver.show', $driver))->getContent(), 'data-label="Trip" data-primary'))->toBe(10);
});
