<?php

use Database\Factories\DriverFactory;
use Database\Factories\TripFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Actors;

/*
| trip_events and fuel_purchases (08 §9): the five CHECKs, unique trip_event_id and the
| same-company FKs, on SQLite and MySQL alike.
*/

beforeEach(function () {
    $this->company = Actors::company();
    $this->trip = TripFactory::new()->forCompany($this->company)->inProgress()->create();
    $this->event = fn (array $values) => DB::table('trip_events')->insertGetId($values + [
        'company_id' => $this->company->id, 'trip_id' => $this->trip->id, 'driver_id' => $this->trip->driver_id,
        'vehicle_id' => $this->trip->vehicle_id, 'occurred_at' => now(),
    ]);
    $this->purchase = fn (array $values) => DB::table('fuel_purchases')->insertGetId($values + [
        'company_id' => $this->company->id, 'trip_id' => $this->trip->id, 'driver_id' => $this->trip->driver_id,
        'vehicle_id' => $this->trip->vehicle_id, 'purchased_at' => now(), 'gallons' => 100, 'price_per_gallon' => 3.9, 'total_amount' => 390,
    ]);
});

it('needs a ticket answer on a police stop, and a reason without a ticket', function () {
    expect(fn () => ($this->event)(['type' => 'police_stop']))->toThrow(QueryException::class)
        ->and(fn () => ($this->event)(['type' => 'police_stop', 'ticket_issued' => false]))->toThrow(QueryException::class);

    ($this->event)(['type' => 'police_stop', 'ticket_issued' => true]);
    ($this->event)(['type' => 'police_stop', 'ticket_issued' => false, 'reason' => 'Warning only']);
});

it('needs the weight answer on a weigh station', function () {
    expect(fn () => ($this->event)(['type' => 'weigh_station']))->toThrow(QueryException::class);

    ($this->event)(['type' => 'weigh_station', 'weight_ok' => false]);
});

it('needs a reason on a stop', function () {
    expect(fn () => ($this->event)(['type' => 'stop']))->toThrow(QueryException::class);

    ($this->event)(['type' => 'stop', 'reason' => 'Rest break']);
    ($this->event)(['type' => 'breakdown']);
});

it('needs the accident-register fields on an accident only', function (array $values) {
    expect(fn () => ($this->event)(['type' => 'accident'] + $values))->toThrow(QueryException::class);
})->with([
    'no fields' => [[]],
    'no hazmat answer' => [['injuries_count' => 0, 'fatalities_count' => 0]],
    'no fatalities' => [['injuries_count' => 1, 'hazmat_released' => false]],
    'no injuries' => [['fatalities_count' => 0, 'hazmat_released' => false]],
]);

it('keeps the accident-register fields off other types', function (string $field, mixed $value) {
    ($this->event)(['type' => 'accident', 'injuries_count' => 2, 'fatalities_count' => 0, 'hazmat_released' => false]);

    expect(fn () => ($this->event)(['type' => 'breakdown', $field => $value]))->toThrow(QueryException::class);
})->with([['injuries_count', 0], ['fatalities_count', 0], ['hazmat_released', false]]);

it('rejects an unknown type', function () {
    expect(fn () => ($this->event)(['type' => 'parking']))->toThrow(QueryException::class);
});

it('allows one purchase per fuel event', function () {
    $event = ($this->event)(['type' => 'fuel']);
    ($this->purchase)(['trip_event_id' => $event, 'fuel_type_id' => DB::table('fuel_types')->insertGetId(['name' => 'Diesel', 'is_active' => true])]);

    expect(fn () => ($this->purchase)(['trip_event_id' => $event]))->toThrow(QueryException::class);
    ($this->purchase)(['trip_event_id' => null]);
});

it('rejects a trip, driver or event of another company', function (string $table, string $column, Closure $foreign) {
    $insert = $table === 'trip_events' ? $this->event : $this->purchase;
    $values = $table === 'trip_events' ? ['type' => 'breakdown'] : [];

    expect(fn () => $insert([$column => $foreign()] + $values))->toThrow(QueryException::class);
})->with([
    'event trip' => ['trip_events', 'trip_id', fn () => TripFactory::new()->inProgress()->create()->id],
    'event driver' => ['trip_events', 'driver_id', fn () => DriverFactory::new()->create()->id],
    'purchase trip' => ['fuel_purchases', 'trip_id', fn () => TripFactory::new()->inProgress()->create()->id],
    'purchase event' => ['fuel_purchases', 'trip_event_id', function () {
        $trip = TripFactory::new()->inProgress()->create();

        return DB::table('trip_events')->insertGetId(['company_id' => $trip->company_id, 'trip_id' => $trip->id, 'driver_id' => $trip->driver_id, 'type' => 'fuel', 'occurred_at' => now()]);
    }],
]);

it('restricts deleting a trip that has events', function () {
    ($this->event)(['type' => 'breakdown']);

    expect(fn () => DB::table('trips')->where('id', $this->trip->id)->delete())->toThrow(QueryException::class);
});
