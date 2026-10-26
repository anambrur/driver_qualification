<?php

use App\Models\AuditLog;
use App\Models\Trip;
use App\Models\TripStop;
use App\Services\Operations\TripService;
use Database\Factories\ShipperFactory;
use Database\Factories\TripFactory;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;

/*
| The trip lifecycle before assignment (10 §13): draft → planned once the number is confirmed
| and the route has a start and a drop-off; cancel from draft, planned or assigned with a reason;
| delete only a draft or cancelled trip. Every refused transition leaves the trip as it was.
*/

beforeEach(function () {
    Shippers::seedStates();
    $this->company = Actors::company();
    $this->owner = $this->company->owner;
});

it('plans a trip when it is confirmed after its stops are set', function () {
    $trip = Trips::create($this->company);
    Trips::service($this->company, fn (TripService $trips) => $trips->saveStops($trip, Trips::route(), $this->owner));
    expect($trip->fresh()->status)->toBe('draft');

    Trips::service($this->company, fn (TripService $trips) => $trips->confirmNumber($trip, $this->owner));

    expect($trip->fresh()->status)->toBe('planned')
        ->and(AuditLog::query()->where('action', 'trip.planned')->where('subject_id', $trip->id)->exists())->toBeTrue();
});

it('plans a trip when its stops are set after it is confirmed', function () {
    $trip = Trips::create($this->company);
    Trips::service($this->company, fn (TripService $trips) => $trips->confirmNumber($trip, $this->owner));
    expect($trip->fresh()->status)->toBe('draft');

    $this->actingAs($this->owner)->put(route('trips.stops.update', $trip), Trips::stopsPayload())
        ->assertRedirect(route('trips.show', $trip).'#stops');

    expect($trip->fresh()->status)->toBe('planned');
});

it('keeps a planned trip planned while its stops change', function () {
    $trip = Trips::confirmAndRoute(Trips::create($this->company), $this->company);

    Trips::service($this->company, fn (TripService $trips) => $trips->saveStops($trip, Trips::route(pickup: true), $this->owner));

    expect($trip->fresh()->status)->toBe('planned')
        ->and(AuditLog::query()->where('action', 'trip.planned')->count())->toBe(1);
});

it('refuses to confirm a trip that is not an unconfirmed draft', function (string $state) {
    $trip = TripFactory::new()->forCompany($this->company)->{$state}()->create();

    expect(fn () => Trips::service($this->company, fn (TripService $trips) => $trips->confirmNumber($trip, $this->owner)))
        ->toThrow(ValidationException::class);
})->with(['confirmed', 'planned', 'assigned', 'inProgress', 'completed', 'cancelled']);

it('says why an unconfirmed cancelled trip cannot be confirmed', function () {
    $trip = TripFactory::new()->forCompany($this->company)->cancelled()->create();

    $this->actingAs($this->owner)->post(route('trips.confirm-number', $trip))
        ->assertSessionHasErrors(['trip' => 'Only a draft trip can have its number and date confirmed.']);
});

it('cancels a draft, planned or assigned trip with its reason', function (string $state) {
    $trip = TripFactory::new()->forCompany($this->company)->{$state}()->create();

    $this->actingAs($this->owner)->post(route('trips.cancel', $trip), ['cancel_reason' => 'Customer called it off'])
        ->assertRedirect(route('trips.show', $trip));

    expect($trip->fresh())->status->toBe('cancelled')->cancel_reason->toBe('Customer called it off')->cancelled_at->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'trip.cancelled')->value('changes'))->toMatchArray(['from' => $trip->status, 'reason' => 'Customer called it off']);
})->with(['draft', 'planned', 'assigned']);

it('needs a reason to cancel', function () {
    $trip = Trips::create($this->company);

    $this->actingAs($this->owner)->post(route('trips.cancel', $trip), ['cancel_reason' => ''])->assertSessionHasErrors('cancel_reason');
    $this->post(route('trips.cancel', $trip), ['cancel_reason' => str_repeat('x', 501)])->assertSessionHasErrors('cancel_reason');

    expect($trip->fresh()->status)->toBe('draft');
});

it('refuses to cancel a trip that is under way, completed or cancelled', function (string $state) {
    $trip = TripFactory::new()->forCompany($this->company)->{$state}()->create();

    $this->actingAs($this->owner)->post(route('trips.cancel', $trip), ['cancel_reason' => 'No'])->assertSessionHasErrors('trip');

    expect($trip->fresh()->status)->toBe($trip->status);
})->with(['inProgress', 'completed', 'cancelled']);

it('deletes a draft or cancelled trip softly and audits it', function (string $state) {
    $trip = TripFactory::new()->forCompany($this->company)->{$state}()->create();

    $this->actingAs($this->owner)->delete(route('trips.destroy', $trip))->assertRedirect(route('trips.index'));

    expect(Trip::withoutTenancy()->withTrashed()->find($trip->id)->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'trip.deleted')->where('subject_id', $trip->id)->value('actor_id'))->toBe($this->owner->id);
    $this->get(route('trips.show', $trip))->assertNotFound();
})->with(['draft', 'cancelled']);

it('refuses to delete a planned, assigned, under-way or completed trip', function (string $state) {
    $trip = TripFactory::new()->forCompany($this->company)->{$state}()->create();

    $this->actingAs($this->owner)->delete(route('trips.destroy', $trip))->assertSessionHasErrors('trip');

    expect($trip->fresh()->trashed())->toBeFalse();
})->with(['planned', 'assigned', 'inProgress', 'completed']);

it('refuses to edit the details or stops of a trip that is under way, completed or cancelled', function (string $state) {
    $trip = TripFactory::new()->forCompany($this->company)->{$state}()->create(['product' => 'Steel coils']);
    $this->actingAs($this->owner);

    $this->put(route('trips.update', $trip), Trips::details())->assertSessionHasErrors('trip');
    $this->put(route('trips.stops.update', $trip), Trips::stopsPayload())->assertSessionHasErrors('trip');

    expect($trip->fresh()->product)->toBe('Steel coils')->and(TripStop::withoutTenancy()->where('trip_id', $trip->id)->count())->toBe(0);
})->with(['inProgress', 'completed', 'cancelled']);

it('edits the details of a planned or assigned trip', function (string $state) {
    $trip = TripFactory::new()->forCompany($this->company)->{$state}()->create();

    $this->actingAs($this->owner)->put(route('trips.update', $trip), Trips::details(['weight' => '18000', 'weight_unit' => 'kg']))
        ->assertRedirect(route('trips.show', $trip).'#details');

    expect($trip->fresh())->weight->toBe('18000.00')->weight_unit->toBe('kg')->planned_distance_miles->toBe('239.5')->status->toBe($trip->status);
})->with(['planned', 'assigned']);

it('creates a trip from the create page and opens its stops', function () {
    $shipper = ShipperFactory::new()->forCompany($this->company)->create();

    $this->actingAs($this->owner)->post(route('trips.store'), Trips::details(['shipper_id' => $shipper->id, 'trip_date' => '2026-10-30']))
        ->assertRedirect();

    $trip = Trip::withoutTenancy()->where('company_id', $this->company->id)->sole();
    expect($trip)->trip_number->toBe(1)->shipper_id->toBe($shipper->id)->product->toBe('Frozen food')->trip_date->toDateString()->toBe('2026-10-30');
});

it('enforces the status rule at the database level as well', function () {
    $trip = TripFactory::new()->forCompany($this->company)->create();

    expect(fn () => Trip::withoutTenancy()->whereKey($trip->id)->update(['status' => 'assigned']))->toThrow(QueryException::class);
});
