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
| status and date filters; search by number, product or shipper; a constant query count. The
| trip page stays within a query budget with all its tabs.
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
