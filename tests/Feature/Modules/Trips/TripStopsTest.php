<?php

use App\Models\TripStop;
use App\Services\Operations\TripService;
use Database\Factories\ShipperFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;

/*
| The planned route (req 6.1, 6.3): one start, at most one pickup (none when the start is the
| pickup) and one drop-off, kept in route order; addresses from the map picker; an optional
| active shipper of the same company per stop.
*/

beforeEach(function () {
    Shippers::seedStates();
    $this->company = Actors::company();
    $this->owner = $this->company->owner;
    $this->trip = Trips::create($this->company);
});

$stops = fn ($trip) => TripStop::withoutTenancy()->where('trip_id', $trip->id)->orderBy('sequence')->get();

it('saves a start and a drop-off from the form', function () use ($stops) {
    $this->actingAs($this->owner)->put(route('trips.stops.update', $this->trip), Trips::stopsPayload())->assertSessionHasNoErrors();

    $saved = $stops($this->trip);
    expect($saved->pluck('stop_type')->all())->toBe(['start', 'drop_off'])
        ->and($saved->pluck('sequence')->all())->toBe([1, 2])
        ->and($saved[0])->city->toBe('Dallas')->place_source->toBe('photon')->place_ref->toBe('W123456')->also_pickup->toBeFalse()
        ->and($saved[1])->city->toBe('Houston')->country_code->toBe('US');
});

it('needs a start and a drop-off', function (string $missing) use ($stops) {
    $payload = Trips::stopsPayload();
    unset($payload['stops'][$missing]);

    $this->actingAs($this->owner)->put(route('trips.stops.update', $this->trip), $payload)->assertSessionHasErrors("stops.{$missing}");

    expect($stops($this->trip))->toBeEmpty();
})->with(['start', 'drop_off']);

it('ignores the pickup fields unless a separate pickup is asked for', function () use ($stops) {
    $payload = Trips::stopsPayload(pickup: true);
    $payload['has_pickup'] = null;

    $this->actingAs($this->owner)->put(route('trips.stops.update', $this->trip), $payload)->assertSessionHasNoErrors();

    expect($stops($this->trip)->pluck('stop_type')->all())->toBe(['start', 'drop_off']);
});

it('refuses a separate pickup when the start is the pickup', function () use ($stops) {
    $this->actingAs($this->owner)->put(route('trips.stops.update', $this->trip), Trips::stopsPayload(pickup: true, alsoPickup: true))
        ->assertSessionHasErrors('stops.pickup');

    $this->put(route('trips.stops.update', $this->trip), Trips::stopsPayload(alsoPickup: true))->assertSessionHasNoErrors();
    expect($stops($this->trip)->first()->also_pickup)->toBeTrue();
});

it('keeps the route in order when a pickup is added and removed, and keeps arrival times', function () use ($stops) {
    $save = fn (bool $pickup) => Trips::service($this->company, fn (TripService $trips) => $trips->saveStops($this->trip, Trips::route(pickup: $pickup), $this->owner));

    $save(false);
    $dropOffId = $stops($this->trip)->last()->id;
    DB::table('trip_stops')->where('id', $dropOffId)->update(['arrived_at' => '2026-10-26 15:00:00']);

    $save(true);
    expect($stops($this->trip)->map(fn ($s) => [$s->stop_type, $s->sequence])->all())->toBe([['start', 1], ['pickup', 2], ['drop_off', 3]]);

    $save(false);
    $saved = $stops($this->trip);
    expect($saved->map(fn ($s) => [$s->stop_type, $s->sequence])->all())->toBe([['start', 1], ['drop_off', 2]])
        ->and($saved->last()->id)->toBe($dropOffId)
        ->and($saved->last()->arrived_at?->toDateTimeString())->toBe('2026-10-26 15:00:00');
});

it('keeps the pickup choices when another form on the trip page fails', function () {
    Trips::service($this->company, fn (TripService $trips) => $trips->saveStops($this->trip, Trips::route(pickup: true), $this->owner));
    $this->actingAs($this->owner);

    $this->from(route('trips.show', $this->trip))->put(route('trips.update', $this->trip), Trips::details(['weight' => 'heavy']))
        ->assertSessionHasErrors('weight');
    $page = $this->get(route('trips.show', $this->trip))->assertOk()->getContent();

    expect($page)->toMatch('/<input type="checkbox" name="has_pickup"[^>]*checked/')
        ->and($page)->toContain('value="Fort Worth"');
});

it('takes an active shipper of the company for a stop', function () use ($stops) {
    $shipper = ShipperFactory::new()->forCompany($this->company)->create();
    $payload = Trips::stopsPayload();
    $payload['stops']['start']['shipper_id'] = $shipper->id;

    $this->actingAs($this->owner)->put(route('trips.stops.update', $this->trip), $payload)->assertSessionHasNoErrors();

    expect($stops($this->trip)->first()->shipper_id)->toBe($shipper->id);
});

it('refuses an inactive shipper or one of another company, but keeps one already chosen', function () {
    $inactive = ShipperFactory::new()->forCompany($this->company)->inactive()->create();
    $foreign = ShipperFactory::new()->forCompany(Actors::company())->create();
    $this->actingAs($this->owner);

    foreach ([$inactive->id => 'Choose an active shipper', $foreign->id => 'invalid'] as $id => $message) {
        $payload = Trips::stopsPayload();
        $payload['stops']['drop_off']['shipper_id'] = $id;
        $this->put(route('trips.stops.update', $this->trip), $payload)->assertSessionHasErrors('stops.drop_off.shipper_id');
    }

    // Chosen while active, deactivated afterwards: the stop keeps it
    $shipper = ShipperFactory::new()->forCompany($this->company)->create();
    $route = Trips::route();
    $route['drop_off']['shipper_id'] = $shipper->id;
    Trips::service($this->company, fn (TripService $trips) => $trips->saveStops($this->trip, $route, $this->owner));
    $shipper->update(['is_active' => false]);

    expect(fn () => Trips::service($this->company, fn (TripService $trips) => $trips->saveStops($this->trip, $route, $this->owner)))->not->toThrow(ValidationException::class);
});

it('applies the address rules of the picker to each stop', function () use ($stops) {
    $payload = Trips::stopsPayload();
    $payload['stops']['drop_off'] = Trips::stop(['city' => 'Toronto', 'state_code' => 'on', 'zip' => 'm5v2t6', 'country_code' => 'ca', 'place_source' => '', 'place_ref' => 'W1']);
    $this->actingAs($this->owner)->put(route('trips.stops.update', $this->trip), $payload)->assertSessionHasNoErrors();

    expect($stops($this->trip)->last())->zip->toBe('M5V 2T6')->state_code->toBe('ON')->country_code->toBe('CA')->place_source->toBe('manual')->place_ref->toBeNull();

    $payload['stops']['drop_off'] = Trips::stop(['country_code' => 'CA']); // TX is not a province
    $this->put(route('trips.stops.update', $this->trip), $payload)->assertSessionHasErrors(['stops.drop_off.state_code', 'stops.drop_off.zip']);

    $payload['stops']['drop_off'] = Trips::stop(['latitude' => '32.7', 'longitude' => '']);
    $this->put(route('trips.stops.update', $this->trip), $payload)->assertSessionHasErrors('stops.drop_off.longitude');
});
