<?php

use App\Authorization\RoleTemplates;
use App\Features\Feature;
use App\Models\Trip;
use App\Models\TripEvent;
use Database\Factories\DocumentFactory;
use Database\Factories\FuelPurchaseFactory;
use Database\Factories\TripEventFactory;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\TripEvents\TripEvents;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| Who reaches trip events (P2-5): trip-events.view to read, trip-events.manage (write, Dispatcher
| template) to record and void; the trip_events feature on every route and on the tab (the
| Company plan has trips without it); nothing written for a read-only company.
*/

beforeEach(function () {
    $this->fuelType = TripEvents::setUp();
});

/**
 * @return list<array{0: string, 1: array<string, mixed>}>
 */
function tripEventWriteRoutes(Trip $trip, TripEvent $event, int $fuelType): array
{
    return [
        [route('trips.events.store', $trip), ['type' => 'breakdown', 'occurred_at' => TripEvents::at($trip->company)]],
        [route('trips.fuel-stops.store', $trip), TripEvents::fuel($trip->company, $fuelType)],
        [route('trip-events.attachments.store', $event), ['slot' => 'other', 'files' => [Docs::pdf()]]],
        [route('trip-events.void', $event), ['void_reason' => 'Mistake']],
    ];
}

it('gives the Dispatcher template trip-events.view and trip-events.manage', function () {
    expect(RoleTemplates::permissionsFor('Dispatcher'))->toContain('trip-events.view', 'trip-events.manage')
        ->not->toContain('trip-events.create.own', 'fuel.create.own');
});

it('reads with trip-events.view only, without any way to record', function () {
    $company = Actors::company();
    $trip = TripEvents::trip($company);
    $event = TripEventFactory::new()->forTrip($trip)->ofType('breakdown')->create();
    $this->actingAs(Actors::memberWithPermissions($company, ['trips.view', 'trip-events.view']));

    $this->get(route('trips.show', $trip))->assertOk()->assertSee('trip-tabs-tab-events', false)
        ->assertDontSee('data-event-type="stop"', false)->assertDontSee('trip-event-void-dialog', false);
    foreach (tripEventWriteRoutes($trip, $event, $this->fuelType) as [$url, $payload]) {
        $this->post($url, $payload)->assertForbidden();
    }
    expect(TripEvent::withoutTenancy()->count())->toBe(1)->and($event->fresh()->voided_at)->toBeNull();
});

it('hides the tab without trip-events.view', function () {
    $company = Actors::company();
    $trip = TripEvents::trip($company);
    $this->actingAs(Actors::memberWithPermissions($company, ['trips.view']));

    $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('trip-tabs-tab-events', false);
});

it('refuses every write for a read-only company but keeps the tab', function () {
    $company = Actors::readOnly(Actors::company());
    $trip = TripEvents::trip($company);
    $event = TripEventFactory::new()->forTrip($trip)->ofType('breakdown')->create();
    $this->actingAs($company->owner);

    $this->get(route('trips.show', $trip))->assertOk()->assertSee('trip-tabs-tab-events', false)->assertDontSee('data-event-type="stop"', false);
    foreach (tripEventWriteRoutes($trip, $event, $this->fuelType) as [$url, $payload]) {
        $this->post($url, $payload)->assertForbidden();
    }
    expect(TripEvent::withoutTenancy()->count())->toBe(1);
});

it('hides the tab and refuses every route without the trip_events feature (Company plan)', function () {
    $company = Actors::company();
    $trip = TripEvents::trip($company);
    $event = TripEventFactory::new()->forTrip($trip)->ofType('fuel')->create();
    $document = DocumentFactory::new()->inFolder(Docs::systemFolder($trip))->create(['trip_id' => null, 'fuel_purchase_id' => FuelPurchaseFactory::new()->forEvent($event)->create()->id]);
    Actors::withPlan($company, collect(Feature::cases())->reject(fn (Feature $f) => $f === Feature::TripEvents || $f->isLimit())->map->value->all());
    $this->actingAs($company->owner);

    $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('trip-tabs-tab-events', false);
    $this->get(route('trip-events.attachments.show', [$event, $document]))->assertForbidden();
    foreach (tripEventWriteRoutes($trip, $event, $this->fuelType) as [$url, $payload]) {
        $this->post($url, $payload)->assertForbidden();
    }
});

it('serves an attachment of the event only, to users who may see the trip folder', function () {
    $company = Actors::company();
    $trip = TripEvents::trip($company);
    $folder = Docs::systemFolder($trip);
    $event = TripEventFactory::new()->forTrip($trip)->ofType('breakdown')->create();
    $own = DocumentFactory::new()->inFolder($folder)->create(['trip_id' => null, 'trip_event_id' => $event->id]);
    $other = DocumentFactory::new()->inFolder($folder)->create(['trip_id' => null, 'trip_event_id' => TripEventFactory::new()->forTrip($trip)->create()->id]);

    $this->actingAs(Actors::memberWithPermissions($company, ['trip-events.view']))
        ->get(route('trip-events.attachments.show', [$event, $own]))->assertForbidden();

    $this->actingAs(Actors::dispatcher($company));
    $this->get(route('trip-events.attachments.show', [$event, $other]))->assertNotFound();
});

it('opens the trip page within a query budget that does not grow with 50 events', function () {
    $company = Actors::company();
    $trip = TripEvents::trip($company);
    $folder = Docs::systemFolder($trip);
    $this->actingAs(Actors::dispatcher($company));

    Perf::assertConstantQueries(function (int $count) use ($trip, $folder) {
        foreach (range(1, $count) as $i) {
            $type = ['stop', 'police_stop', 'weigh_station', 'accident', 'breakdown', 'fuel'][$i % 6];
            $event = TripEventFactory::new()->forTrip($trip)->ofType($type)->create(['occurred_at' => now()->subMinutes($i)]);
            $subject = ['trip_event_id' => $event->id];
            if ($type === 'fuel') {
                $subject = ['fuel_purchase_id' => FuelPurchaseFactory::new()->forEvent($event)->confirmed()->create()->id];
            }
            DocumentFactory::new()->inFolder($folder)->create(['trip_id' => null] + $subject);
        }
    }, fn () => $this->get(route('trips.show', $trip))->assertOk()->assertSee('trip-tabs-tab-events', false), small: 2, large: 50);
});
