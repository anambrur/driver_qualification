<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\FuelPurchase;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Services\Operations\TripService;
use Database\Seeders\InspectionTemplateSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Exports\Exports;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\TripEvents\TripEvents;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;

/*
| Phase 2 acceptance (13; 10 §13): one trip walked from draft to completed on the web, the way the
| office runs it. The dispatcher plans, assigns, inspects, starts, records events and fuel, picks up
| and completes; the owner confirms the number and downloads the trip ZIP. The dashboard counts
| follow the trip's state.
*/

it('walks a trip from draft to completed on the web (acceptance)', function () {
    $fuelType = TripEvents::setUp();
    (new InspectionTemplateSeeder)->run();
    $company = Actors::company();
    $owner = $company->owner;
    $dispatcher = Actors::dispatcher($company);
    $crew = Trips::crew($company);
    $counts = fn () => Trips::service($company, fn (TripService $trips) => $trips->roadCounts());

    // Shipper saved for reuse (4.4)
    $this->actingAs($owner)->post(route('shippers.store'), Shippers::payload())->assertSessionHasNoErrors();
    $shipper = Shippers::latest($company);

    // Draft with details, then stops with a pickup at the saved shipper (4.1–4.3, 6.1, 6.3)
    $this->actingAs($dispatcher)->post(route('trips.store'), Trips::details(['shipper_id' => $shipper->id]))->assertSessionHasNoErrors();
    $trip = Trip::withoutTenancy()->where('company_id', $company->id)->sole();
    $stops = Trips::stopsPayload(pickup: true);
    $stops['stops']['pickup']['shipper_id'] = $shipper->id;
    $this->put(route('trips.stops.update', $trip), $stops)->assertSessionHasNoErrors();
    expect($trip->fresh()->status)->toBe('draft')
        ->and($counts())->toBe(['on_road' => 0, 'pending_pickup' => 0]);

    // The owner confirms the number and date: planned (4.2)
    $this->actingAs($owner)->post(route('trips.confirm-number', $trip))->assertSessionHasNoErrors();
    expect($trip->fresh()->status)->toBe('planned')
        ->and($counts())->toBe(['on_road' => 0, 'pending_pickup' => 0]);

    // Assignment and equipment (4.5, 4.6)
    $this->actingAs($dispatcher)->put(route('trips.assignment.update', $trip), [
        'driver_id' => $crew['driver']->id, 'vehicle_id' => $crew['vehicle']->id, 'trailer_id' => $crew['trailer']->id,
    ])->assertSessionHasNoErrors();
    $this->post(route('trips.equipment.confirm', $trip))->assertSessionHasNoErrors();
    expect($trip->fresh())->status->toBe('assigned')->equipment_confirmed_at->not->toBeNull()
        ->and($counts())->toBe(['on_road' => 0, 'pending_pickup' => 1]);

    // Pre-trip inspection on the seeded checklist, every item passed (5.1–5.3)
    $this->post(route('trips.inspections.store', $trip))->assertSessionHasNoErrors();
    $inspection = Inspection::withoutTenancy()->where('trip_id', $trip->id)->sole();
    $items = InspectionTemplate::query()->findOrFail($inspection->template_id)->items()->pluck('id');
    $this->putJson(route('inspections.results.update', $inspection), [
        'results' => $items->mapWithKeys(fn (int $id) => [$id => ['result' => 'pass', 'note' => null]])->all(),
    ])->assertOk();
    $this->post(route('inspections.complete', $inspection), ['odometer' => '182000'])->assertSessionHasNoErrors();
    expect($inspection->fresh()->status)->toBe('completed');

    // Start: in progress, on the road and still waiting for the pickup (9.2)
    $this->post(route('trips.start', $trip))->assertSessionHasNoErrors();
    expect($trip->fresh()->status)->toBe('in_progress')
        ->and($counts())->toBe(['on_road' => 1, 'pending_pickup' => 1]);
    $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->assertViewHas('tripStats', ['on_road' => 1, 'pending_pickup' => 1]);
    $this->actingAs($dispatcher);

    // Events and a fuel stop with their documents (7.1, 7.2, 7.6)
    $this->travel(2)->hours();
    $this->post(route('trips.events.store', $trip), ['type' => 'stop', 'reason' => 'Rest break', 'occurred_at' => TripEvents::at($company, -90)])
        ->assertSessionHasNoErrors();
    $this->post(route('trips.events.store', $trip), [
        'type' => 'police_stop', 'ticket_issued' => '1', 'occurred_at' => TripEvents::at($company, -60), 'files' => ['ticket' => [Docs::pdf('ticket.pdf')]],
    ])->assertSessionHasNoErrors();
    $this->post(route('trips.fuel-stops.store', $trip), TripEvents::fuel($company, $fuelType))->assertSessionHasNoErrors();
    $purchase = FuelPurchase::withoutTenancy()->where('trip_id', $trip->id)->sole();
    expect($purchase->confirmed_at)->toBeNull();
    $this->post(route('trip-events.attachments.store', $purchase->trip_event_id), ['slot' => 'receipt', 'files' => [Docs::pdf('receipt.pdf')]])
        ->assertSessionHasNoErrors();
    expect($purchase->fresh()->confirmed_at)->not->toBeNull()
        ->and(TripEvent::withoutTenancy()->where('trip_id', $trip->id)->pluck('type')->sort()->values()->all())->toBe(['fuel', 'police_stop', 'stop'])
        ->and(Document::withoutTenancy()->where('folder_id', $trip->folder()->withoutGlobalScopes()->value('id'))->count())->toBe(2);
    $this->get(route('trips.show', $trip))->assertOk()->assertSee('Police stop')->assertSee("Love's #301");

    // Picked up, then completed (10 §13 step 5)
    $this->post(route('trips.picked-up', $trip))->assertSessionHasNoErrors();
    expect($counts())->toBe(['on_road' => 1, 'pending_pickup' => 0]);
    $this->post(route('trips.complete', $trip), ['actual_distance_miles' => '244.0'])->assertSessionHasNoErrors();
    expect($trip->fresh())->status->toBe('completed')->completed_at->not->toBeNull()
        ->and($counts())->toBe(['on_road' => 0, 'pending_pickup' => 0]);
    $this->get(route('trips.show', $trip))->assertOk();

    // The owner downloads the trip ZIP with the ticket and the receipt (2.5)
    $this->actingAs($owner)->post(route('trips.export', $trip))->assertRedirect(route('exports.index'));
    $export = Exports::latest($company);
    $base = "Trips/{$trip->trip_number} (#{$trip->id})/Trip documents/";
    $entries = collect(Exports::entries($export));
    expect($export->status)->toBe('ready')
        ->and($entries)->toHaveCount(2)
        ->and($entries->every(fn (string $name) => str_starts_with($name, $base)))->toBeTrue()
        ->and($entries->contains(fn (string $name) => str_contains($name, 'Police ticket')))->toBeTrue()
        ->and($entries->contains(fn (string $name) => str_contains($name, 'Fuel receipt')))->toBeTrue();

    expect(AuditLog::query()->where('company_id', $company->id)->pluck('action')->unique()->all())->toContain(
        'trip.created', 'trip.number_confirmed', 'trip.planned', 'trip.assigned', 'trip.equipment_confirmed',
        'trip.started', 'trip_event.recorded', 'fuel_purchase.confirmed', 'trip.picked_up', 'trip.completed', 'export.requested',
    );
    expect(Artisan::call('integrity:check'))->toBe(0, Artisan::output());
});
