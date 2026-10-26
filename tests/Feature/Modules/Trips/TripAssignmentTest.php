<?php

use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Operations\TripAssignmentService;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverComplianceDocumentFactory;
use Database\Factories\DriverFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\TripFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;

/*
| Assignment and equipment confirmation (req 4.5, 4.6; 10 §13 step 2): an active driver, a vehicle
| and an optional trailer of the company, none deleted or transferred out; the vehicle and trailer
| default to the driver's asset group; any change clears the equipment confirmation.
*/

beforeEach(function () {
    Shippers::seedStates();
    $this->company = Actors::company();
    $this->crew = Trips::crew($this->company);
    $this->actingAs(Actors::dispatcher($this->company));
});

function assignPayload(array $crew, array $overrides = []): array
{
    return array_merge(['driver_id' => $crew['driver']->id, 'vehicle_id' => $crew['vehicle']->id, 'trailer_id' => $crew['trailer']->id], $overrides);
}

it('assigns a planned trip and reassigns it while assigned', function () {
    $trip = Trips::planned($this->company);

    $this->put(route('trips.assignment.update', $trip), assignPayload($this->crew))
        ->assertRedirect(route('trips.show', $trip))->assertSessionHasNoErrors();

    $trip->refresh();
    expect($trip->status)->toBe('assigned')
        ->and([$trip->driver_id, $trip->vehicle_id, $trip->trailer_id])->toBe([$this->crew['driver']->id, $this->crew['vehicle']->id, $this->crew['trailer']->id]);

    $otherVehicle = VehicleFactory::new()->forCompany($this->company)->create();
    $this->put(route('trips.assignment.update', $trip), assignPayload($this->crew, ['vehicle_id' => $otherVehicle->id, 'trailer_id' => null]))->assertSessionHasNoErrors();

    $trip->refresh();
    expect($trip->vehicle_id)->toBe($otherVehicle->id)->and($trip->trailer_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'trip.assigned')->where('subject_id', $trip->id)->count())->toBe(2);
});

it('unassigns an assigned trip back to planned', function () {
    $trip = Trips::assigned($this->company);

    $this->delete(route('trips.assignment.destroy', $trip))->assertRedirect(route('trips.show', $trip));

    $trip->refresh();
    expect($trip->status)->toBe('planned')
        ->and([$trip->driver_id, $trip->vehicle_id, $trip->trailer_id, $trip->equipment_confirmed_at])->toBe([null, null, null, null])
        ->and(AuditLog::query()->where('action', 'trip.unassigned')->where('subject_id', $trip->id)->exists())->toBeTrue();
});

it('takes the vehicle and trailer from the driver\'s newest active asset group when the vehicle is empty', function () {
    $older = AssetGroupFactory::new()->forCompany($this->company)->withTrailer()->create(['driver_id' => $this->crew['driver']->id]);
    $newer = AssetGroupFactory::new()->forCompany($this->company)->withTrailer()->create(['driver_id' => $this->crew['driver']->id]);
    AssetGroupFactory::new()->forCompany($this->company)->create(['driver_id' => $this->crew['driver']->id, 'status' => 'inactive']);
    $trip = Trips::planned($this->company);

    $this->put(route('trips.assignment.update', $trip), ['driver_id' => $this->crew['driver']->id])->assertSessionHasNoErrors();

    expect($trip->refresh()->vehicle_id)->toBe($newer->vehicle_id)->and($trip->trailer_id)->toBe($newer->trailer_id)
        ->and($older->vehicle_id)->not->toBe($newer->vehicle_id);
});

it('keeps a chosen trailer and skips an asset group whose vehicle is gone', function () {
    $group = AssetGroupFactory::new()->forCompany($this->company)->withTrailer()->create(['driver_id' => $this->crew['driver']->id]);
    $trip = Trips::planned($this->company);

    $this->put(route('trips.assignment.update', $trip), ['driver_id' => $this->crew['driver']->id, 'trailer_id' => $this->crew['trailer']->id])->assertSessionHasNoErrors();
    expect($trip->refresh()->vehicle_id)->toBe($group->vehicle_id)->and($trip->trailer_id)->toBe($this->crew['trailer']->id);

    Vehicle::withoutTenancy()->whereKey($group->vehicle_id)->update(['transferred_out_at' => now()]);
    $other = Trips::planned($this->company);

    $this->put(route('trips.assignment.update', $other), ['driver_id' => $this->crew['driver']->id])->assertSessionHasErrors('vehicle_id');
    expect($other->refresh()->status)->toBe('planned');
});

it('gives the page script the asset-group defaults per driver', function () {
    $group = AssetGroupFactory::new()->forCompany($this->company)->create(['driver_id' => $this->crew['driver']->id]);

    $defaults = Trips::operate($this->company, fn (TripAssignmentService $assignment) => $assignment->groupDefaults());

    expect($defaults)->toBe([$this->crew['driver']->id => ['vehicle_id' => $group->vehicle_id, 'trailer_id' => null]]);
});

it('refuses a driver who is not active', function (Closure $driver) {
    $trip = Trips::planned($this->company);

    $this->put(route('trips.assignment.update', $trip), assignPayload($this->crew, ['driver_id' => $driver($this->company)->id]))->assertSessionHasErrors('driver_id');

    expect($trip->refresh()->status)->toBe('planned');
})->with([
    'terminated' => [fn ($company) => DriverFactory::new()->forCompany($company)->employment('terminated')->create()],
    'not hired' => [fn ($company) => DriverFactory::new()->forCompany($company)->application('pending')->create()],
    'inactive' => [fn ($company) => DriverFactory::new()->forCompany($company)->employment('inactive')->create()],
]);

it('refuses a deleted driver', function () {
    $trip = Trips::planned($this->company);
    $this->crew['driver']->delete();

    $this->put(route('trips.assignment.update', $trip), assignPayload($this->crew))->assertSessionHasErrors('driver_id');
});

it('refuses a deleted or transferred-out vehicle or trailer', function (string $field, string $change) {
    $trip = Trips::planned($this->company);
    $asset = $this->crew[str_replace('_id', '', $field)];
    $change === 'deleted' ? $asset->delete() : $asset->forceFill(['transferred_out_at' => now()])->save();

    $this->put(route('trips.assignment.update', $trip), assignPayload($this->crew))->assertSessionHasErrors($field);

    expect($trip->refresh()->status)->toBe('planned');
})->with([
    'deleted vehicle' => ['vehicle_id', 'deleted'],
    'transferred-out vehicle' => ['vehicle_id', 'transferred'],
    'deleted trailer' => ['trailer_id', 'deleted'],
    'transferred-out trailer' => ['trailer_id', 'transferred'],
]);

it('refuses another company\'s driver, vehicle or trailer', function (string $field, Closure $foreign) {
    $trip = Trips::planned($this->company);

    $this->putJson(route('trips.assignment.update', $trip), assignPayload($this->crew, [$field => $foreign()->id]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($trip->refresh()->status)->toBe('planned');
})->with([
    'driver' => ['driver_id', fn () => DriverFactory::new()->create()],
    'vehicle' => ['vehicle_id', fn () => VehicleFactory::new()->create()],
    'trailer' => ['trailer_id', fn () => TrailerFactory::new()->create()],
]);

it('assigns only a planned or assigned trip', function (string $state) {
    $trip = TripFactory::new()->forCompany($this->company)->{$state}()->create();

    $this->putJson(route('trips.assignment.update', $trip), assignPayload($this->crew))->assertUnprocessable()->assertJsonValidationErrors('trip');
})->with(['draft', 'inProgress', 'completed', 'cancelled']);

it('confirms the equipment once, for an assigned trip', function () {
    $trip = Trips::assigned($this->company, $this->crew, confirmed: false);
    $dispatcher = auth()->user();

    $this->post(route('trips.equipment.confirm', $trip))->assertRedirect(route('trips.show', $trip));

    $trip->refresh();
    expect($trip->equipment_confirmed_at)->not->toBeNull()->and($trip->equipment_confirmed_by_user_id)->toBe($dispatcher->id);
    $audit = AuditLog::query()->where('action', 'trip.equipment_confirmed')->where('subject_id', $trip->id)->sole();
    expect($audit->changes['vehicle_unit_no'])->toBe($this->crew['vehicle']->unit_no);

    $this->postJson(route('trips.equipment.confirm', $trip))->assertUnprocessable();
    $this->postJson(route('trips.equipment.confirm', Trips::planned($this->company)))->assertUnprocessable();
});

it('clears the equipment confirmation when the driver, vehicle or trailer changes', function (string $field) {
    $trip = Trips::assigned($this->company, $this->crew);
    $replacement = match ($field) {
        'driver_id' => DriverFactory::new()->forCompany($this->company)->create(),
        'vehicle_id' => VehicleFactory::new()->forCompany($this->company)->create(),
        'trailer_id' => TrailerFactory::new()->forCompany($this->company)->create(),
    };

    $this->put(route('trips.assignment.update', $trip), assignPayload($this->crew, [$field => $replacement->id]))->assertSessionHasNoErrors();

    expect($trip->refresh()->equipment_confirmed_at)->toBeNull()->and($trip->equipment_confirmed_by_user_id)->toBeNull();
})->with(['driver_id', 'vehicle_id', 'trailer_id']);

it('keeps the equipment confirmation when the same crew is saved again', function () {
    $trip = Trips::assigned($this->company, $this->crew);
    $audits = AuditLog::query()->where('action', 'trip.assigned')->count();

    $this->put(route('trips.assignment.update', $trip), assignPayload($this->crew))->assertSessionHasNoErrors();

    expect($trip->refresh()->equipment_confirmed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'trip.assigned')->count())->toBe($audits);
});

it('warns about an expired license, an expired medical card and another assigned trip', function () {
    $driver = DriverFactory::new()->forCompany($this->company)->withCurrentLicense(now()->subDay()->toDateString())->create();
    $medical = DocumentType::query()->where('system_key', 'medical_examiner_certificate')->first()
        ?? DocumentTypeFactory::new()->requiresExpiry()->create(['system_key' => 'medical_examiner_certificate']);
    DriverComplianceDocumentFactory::new()->forDriver($driver, $medical)->create(['expiry_date' => now()->subWeek()->toDateString()]);
    $crew = ['driver' => $driver] + $this->crew;
    $first = Trips::assigned($this->company, $crew, confirmed: false);
    $second = Trips::assigned($this->company, $crew, confirmed: false);

    $this->get(route('trips.show', $second))->assertOk()
        ->assertSee('Check before the trip starts')
        ->assertSee('The driver&#039;s license expired on', false)
        ->assertSee('The driver&#039;s medical card expired on', false)
        ->assertSee('The driver is also on trip '.$first->label())
        ->assertSee('The vehicle is also on trip '.$first->label());

    $this->get(route('trips.show', $first))->assertSee('· on trip '.$second->label());
});

it('shows no warning for a driver with valid papers and no other trip', function () {
    $trip = Trips::assigned($this->company, $this->crew, confirmed: false);

    $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('Check before the trip starts');
});

it('offers only assignable drivers and assets in the pickers, plus the current picks', function () {
    $terminated = DriverFactory::new()->forCompany($this->company)->employment('terminated')->create();
    $gone = VehicleFactory::new()->forCompany($this->company)->create(['transferred_out_at' => now()]);
    $trip = Trips::planned($this->company);

    $options = Trips::operate($this->company, fn (TripAssignmentService $assignment) => $assignment->options($trip));

    expect($options['drivers'])->toHaveKey($this->crew['driver']->id)->not->toHaveKey($terminated->id)
        ->and($options['vehicles'])->toHaveKey($this->crew['vehicle']->id)->not->toHaveKey($gone->id)
        ->and($options['trailers'])->toHaveKey($this->crew['trailer']->id);

    $assigned = Trips::assigned($this->company, $this->crew);
    $this->crew['driver']->forceFill(['employment_status' => 'terminated'])->save();
    $options = Trips::operate($this->company, fn (TripAssignmentService $assignment) => $assignment->options(Trip::find($assigned->id)));

    expect($options['drivers'])->toHaveKey($this->crew['driver']->id);
});

it('keeps a deleted current vehicle in the picker so it is not swapped silently', function () {
    $trip = Trips::assigned($this->company, $this->crew);
    $this->crew['vehicle']->delete();

    $options = Trips::operate($this->company, fn (TripAssignmentService $assignment) => $assignment->options(Trip::withoutTenancy()->find($trip->id)));
    expect($options['vehicles'])->toHaveKey($this->crew['vehicle']->id);

    $this->put(route('trips.assignment.update', $trip), assignPayload($this->crew))->assertSessionHasErrors('vehicle_id');
});
