<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\TripEvent;
use Database\Factories\TripEventFactory;
use Database\Factories\TripFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\TripEvents\TripEvents;
use Tests\Support\Actors;

/*
| Trip events recorded by a dispatcher (req 7.1–7.5, P2-5): driver and vehicle from the trip, the
| fields of each type, the required files (08 §9), the recording window, the time bounds, files in
| the trip folder, late files, and void instead of delete.
*/

beforeEach(function () {
    TripEvents::setUp();
    $this->company = Actors::company();
    $this->trip = TripEvents::trip($this->company);
    $this->actingAs(Actors::dispatcher($this->company));
    $this->record = fn (array $data, $trip = null) => $this->post(route('trips.events.store', $trip ?? $this->trip), $data + ['occurred_at' => TripEvents::at($this->company)]);
});

function lastEvent(): TripEvent
{
    return TripEvent::withoutTenancy()->latest('id')->firstOrFail();
}

it('records a stop with the trip\'s driver and vehicle, in UTC, audited', function () {
    $typed = TripEvents::at($this->company, -45);

    $this->post(route('trips.events.store', $this->trip), ['type' => 'stop', 'occurred_at' => $typed, 'reason' => 'Rest break', 'city' => 'Waco', 'state_code' => 'tx'])
        ->assertRedirect(route('trips.show', $this->trip).'#events')->assertSessionHasNoErrors();

    $event = lastEvent();
    expect($event->only(['trip_id', 'driver_id', 'vehicle_id', 'type', 'reason', 'city', 'state_code']))->toBe([
        'trip_id' => $this->trip->id, 'driver_id' => $this->trip->driver_id, 'vehicle_id' => $this->trip->vehicle_id,
        'type' => 'stop', 'reason' => 'Rest break', 'city' => 'Waco', 'state_code' => 'TX',
    ])->and($event->occurred_at->timezone('America/Chicago')->format('Y-m-d\TH:i'))->toBe($typed)
        ->and(AuditLog::query()->where('action', 'trip_event.recorded')->where('subject_id', $event->id)->exists())->toBeTrue();
});

it('needs a reason for a stop', function () {
    ($this->record)(['type' => 'stop'])->assertSessionHasErrors('reason');

    expect(TripEvent::withoutTenancy()->count())->toBe(0);
});

it('needs the ticket file when a police stop issued a ticket', function () {
    ($this->record)(['type' => 'police_stop', 'ticket_issued' => '1'])->assertSessionHasErrors('files.ticket');
    ($this->record)(['type' => 'police_stop', 'ticket_issued' => '0'])->assertSessionHasErrors('reason');
    ($this->record)(['type' => 'police_stop'])->assertSessionHasErrors('ticket_issued');
    expect(TripEvent::withoutTenancy()->count())->toBe(0);

    ($this->record)(['type' => 'police_stop', 'ticket_issued' => '1', 'files' => ['ticket' => [Docs::pdf('ticket.pdf')]]])->assertSessionHasNoErrors();

    $event = lastEvent();
    $document = Document::withoutTenancy()->where('trip_event_id', $event->id)->sole();
    expect($event->ticket_issued)->toBeTrue()
        ->and($document->folder_id)->toBe($this->trip->folder()->withoutGlobalScopes()->value('id'))
        ->and($document->trip_id)->toBeNull()
        ->and($document->document_type_id)->toBe(DocumentType::query()->where('system_key', 'trip_police_ticket')->value('id'))
        ->and($document->title)->toStartWith('Police ticket · Police stop ');
});

it('records a police stop without a ticket when a reason is given', function () {
    ($this->record)(['type' => 'police_stop', 'ticket_issued' => '0', 'reason' => 'Warning for a light'])->assertSessionHasNoErrors();

    expect(lastEvent()->only(['ticket_issued', 'reason']))->toBe(['ticket_issued' => false, 'reason' => 'Warning for a light']);
});

it('needs documents for a weigh station only when the weight is not OK', function () {
    ($this->record)(['type' => 'weigh_station'])->assertSessionHasErrors('weight_ok');
    ($this->record)(['type' => 'weigh_station', 'weight_ok' => '0'])->assertSessionHasErrors('files');
    ($this->record)(['type' => 'weigh_station', 'weight_ok' => '1'])->assertSessionHasNoErrors();
    ($this->record)(['type' => 'weigh_station', 'weight_ok' => '0', 'files' => ['other' => [Docs::pdf('scale.pdf')]]])->assertSessionHasNoErrors();

    $event = lastEvent();
    expect($event->weight_ok)->toBeFalse()
        ->and(Document::withoutTenancy()->where('trip_event_id', $event->id)->value('document_type_id'))->toBeNull();
});

it('records an accident with the register fields and at least one document', function () {
    $register = ['type' => 'accident', 'injuries_count' => '1', 'fatalities_count' => '0', 'hazmat_released' => '0'];

    ($this->record)(['type' => 'accident'])->assertSessionHasErrors(['injuries_count', 'fatalities_count', 'hazmat_released']);
    ($this->record)($register)->assertSessionHasErrors('files');
    ($this->record)($register + ['files' => [
        'licence' => [Docs::pdf('licence.pdf')],
        'insurance' => [Docs::pdf('insurance.pdf')],
        'photos' => [Docs::pdf('front.pdf'), Docs::pdf('rear.pdf')],
    ]])->assertSessionHasNoErrors();

    $event = lastEvent();
    $types = Document::withoutTenancy()->where('trip_event_id', $event->id)->join('document_types', 'document_types.id', '=', 'documents.document_type_id')->pluck('document_types.system_key')->sort()->values()->all();
    expect($event->only(['injuries_count', 'fatalities_count', 'hazmat_released']))->toBe(['injuries_count' => 1, 'fatalities_count' => 0, 'hazmat_released' => false])
        ->and($types)->toBe(['trip_accident_photo', 'trip_accident_photo', 'trip_driver_licence', 'trip_insurance_card']);
});

it('keeps only the fields and file slots of the type', function () {
    ($this->record)(['type' => 'breakdown', 'reason' => 'Flat tyre', 'injuries_count' => '3', 'ticket_issued' => '1', 'weight_ok' => '0', 'files' => ['ticket' => [Docs::pdf()]]])->assertSessionHasNoErrors();

    $event = lastEvent();
    expect($event->only(['reason', 'injuries_count', 'ticket_issued', 'weight_ok']))->toBe(['reason' => 'Flat tyre', 'injuries_count' => null, 'ticket_issued' => null, 'weight_ok' => null])
        ->and(Document::withoutTenancy()->where('trip_event_id', $event->id)->exists())->toBeFalse();
});

it('turns a photo into a PDF and keeps the image', function () {
    ($this->record)(['type' => 'breakdown', 'files' => ['other' => [UploadedFile::fake()->image('engine.jpg', 800, 600)]]])->assertSessionHasNoErrors();

    $document = Document::withoutTenancy()->where('trip_event_id', lastEvent()->id)->sole();
    expect($document->original_file_id)->not->toBeNull();
});

it('records on a trip in progress or completed less than a week ago only', function (Closure $factory, bool $allowed) {
    $trip = $factory()->forCompany($this->company)->create();
    $at = ($trip->started_at ?? now()->subMinutes(30))->addMinutes(5)->timezone('America/Chicago')->format('Y-m-d\TH:i');

    $response = $this->post(route('trips.events.store', $trip), ['type' => 'breakdown', 'occurred_at' => $at]);

    $allowed ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('event');
    expect(TripEvent::withoutTenancy()->where('trip_id', $trip->id)->exists())->toBe($allowed);
})->with([
    'draft' => [fn () => TripFactory::new(), false],
    'planned' => [fn () => TripFactory::new()->confirmed()->state(['status' => 'planned']), false],
    'assigned' => [fn () => TripFactory::new()->assigned(), false],
    'cancelled' => [fn () => TripFactory::new()->cancelled(), false],
    'completed today' => [fn () => TripFactory::new()->completed(), true],
    'completed 6 days ago' => [fn () => TripFactory::new()->completed()->state(['started_at' => now()->subDays(6)->subHours(5), 'completed_at' => now()->subDays(6)]), true],
    'completed 8 days ago' => [fn () => TripFactory::new()->completed()->state(['started_at' => now()->subDays(8)->subHours(5), 'completed_at' => now()->subDays(8)]), false],
]);

it('takes a time between the start and now, or the completion', function () {
    ($this->record)(['type' => 'breakdown', 'occurred_at' => TripEvents::at($this->company, -240)])->assertSessionHasErrors('occurred_at');
    ($this->record)(['type' => 'breakdown', 'occurred_at' => TripEvents::at($this->company, 60)])->assertSessionHasErrors('occurred_at');

    $completed = TripFactory::new()->forCompany($this->company)->completed()->create(['completed_at' => now()->subHours(2)]);
    ($this->record)(['type' => 'breakdown', 'occurred_at' => TripEvents::at($this->company, -60)], $completed)->assertSessionHasErrors('occurred_at');
    ($this->record)(['type' => 'breakdown', 'occurred_at' => TripEvents::at($this->company, -180)], $completed)->assertSessionHasNoErrors();
});

it('adds late files into one of the type\'s slots', function () {
    $event = TripEventFactory::new()->forTrip($this->trip)->ofType('police_stop')->create();

    $this->post(route('trip-events.attachments.store', $event), ['slot' => 'photos', 'files' => [Docs::pdf()]])->assertSessionHasErrors('slot');
    $this->post(route('trip-events.attachments.store', $event), ['slot' => 'ticket', 'files' => [Docs::pdf('late.pdf')]])->assertSessionHasNoErrors();

    expect(Document::withoutTenancy()->where('trip_event_id', $event->id)->count())->toBe(1);
});

it('voids an event with a reason instead of deleting it, keeping its files', function () {
    ($this->record)(['type' => 'police_stop', 'ticket_issued' => '1', 'files' => ['ticket' => [Docs::pdf()]]]);
    $event = lastEvent();

    $this->post(route('trip-events.void', $event), [])->assertSessionHasErrors('void_reason');
    $this->post(route('trip-events.void', $event), ['void_reason' => 'Wrong trip'])->assertSessionHasNoErrors();

    $event->refresh();
    expect($event->voided_at)->not->toBeNull()->and($event->void_reason)->toBe('Wrong trip')
        ->and(Document::withoutTenancy()->where('trip_event_id', $event->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'trip_event.voided')->where('subject_id', $event->id)->value('changes'))->toMatchArray(['reason' => 'Wrong trip']);

    $this->post(route('trip-events.void', $event), ['void_reason' => 'Again'])->assertSessionHasErrors('event');
    $this->post(route('trip-events.attachments.store', $event), ['slot' => 'ticket', 'files' => [Docs::pdf()]])->assertSessionHasErrors('event');
    expect(collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'events') && in_array('DELETE', $route->methods(), true)))->toBeEmpty();
    expect(TripEvent::withoutTenancy()->whereKey($event->id)->exists())->toBeTrue();
});

it('shows the timeline newest first with voided events struck through', function () {
    TripEventFactory::new()->forTrip($this->trip)->create(['reason' => 'Older stop', 'occurred_at' => now()->subHours(2)]);
    TripEventFactory::new()->forTrip($this->trip)->voided('Recorded twice')->create(['reason' => 'Newer stop', 'occurred_at' => now()->subHour()]);

    $this->get(route('trips.show', $this->trip))->assertOk()
        ->assertSee('trip-tabs-tab-events', false)
        ->assertSeeInOrder(['Newer stop', 'Older stop'])
        ->assertSee('Recorded twice')
        ->assertSee('data-voided', false)
        ->assertSee('Police stop')->assertSee('data-dialog-open="trip-fuel-dialog"', false);
});

it('keeps event files out of the folder\'s delete, as evidence', function () {
    ($this->record)(['type' => 'breakdown', 'files' => ['other' => [Docs::pdf()]]]);
    $document = Document::withoutTenancy()->where('trip_event_id', lastEvent()->id)->sole();

    $this->delete(route('documents.destroy', $document))->assertForbidden();

    expect(Document::withoutTenancy()->whereKey($document->id)->exists())->toBeTrue();
});

it('asks for a reason when a JSON police stop says false', function () {
    $this->postJson(route('trips.events.store', $this->trip), ['type' => 'police_stop', 'ticket_issued' => false, 'occurred_at' => TripEvents::at($this->company)])
        ->assertJsonValidationErrors('reason');
});

it('keeps a coordinate of 0', function () {
    ($this->record)(['type' => 'breakdown', 'latitude' => '0', 'longitude' => '-96.7970000'])->assertSessionHasNoErrors();

    expect(lastEvent()->latitude)->toBe('0.0000000');
});
