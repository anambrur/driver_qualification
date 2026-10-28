<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Inspection;
use App\Models\InspectionResult;
use App\Models\InspectionTemplate;
use App\Models\InspectionTemplateItem;
use App\Models\Trip;
use App\Services\Operations\TripAssignmentService;
use Database\Factories\DocumentFactory;
use Database\Factories\InspectionTemplateFactory;
use Database\Factories\TrailerFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| Pre-trip inspections on a trip (req 5.1–5.3, P2-4), recorded by a dispatcher: start from an
| assigned trip on the active template, answer on one screen (saved as you go), progress =
| answered ÷ items, complete once every required item is answered, result from the failed items,
| photos of failed items in the trip folder, and the pre-trip gate on the start.
*/

beforeEach(function () {
    Shippers::seedStates();
    $this->company = Actors::company();
    $this->template = InspectionTemplateFactory::new()->active()->withItems()->create();
    $this->items = $this->template->items()->get();
    $this->actingAs(Actors::dispatcher($this->company));
});

function startInspection(Trip $trip): Inspection
{
    test()->post(route('trips.inspections.store', $trip))->assertRedirect()->assertSessionHasNoErrors();

    return Inspection::withoutTenancy()->where('trip_id', $trip->id)->latest('id')->firstOrFail();
}

/**
 * @param  array<int, string>  $results  template item id => pass|fail|na
 */
function answer(Inspection $inspection, array $results): TestResponse
{
    return test()->putJson(route('inspections.results.update', $inspection), ['results' => collect($results)->map(fn ($result) => ['result' => $result, 'note' => null])->all()]);
}

it('starts from an assigned trip with its crew on the active version, once', function () {
    $crew = Trips::crew($this->company);
    $trip = Trips::assigned($this->company, $crew);

    $inspection = startInspection($trip);

    expect($inspection->only(['driver_id', 'vehicle_id', 'trailer_id', 'template_id', 'status']))->toBe([
        'driver_id' => $crew['driver']->id, 'vehicle_id' => $crew['vehicle']->id, 'trailer_id' => $crew['trailer']->id,
        'template_id' => $this->template->id, 'status' => 'in_progress',
    ]);

    $this->post(route('trips.inspections.store', $trip))->assertRedirect(route('inspections.show', $inspection));
    expect(Inspection::withoutTenancy()->where('trip_id', $trip->id)->count())->toBe(1);
});

it('starts only on an assigned trip and only with an active version', function () {
    $this->post(route('trips.inspections.store', Trips::planned($this->company)))->assertSessionHasErrors(['inspection' => 'Only an assigned trip can be inspected.']);

    InspectionTemplate::query()->update(['is_active' => false]);
    $this->post(route('trips.inspections.store', Trips::assigned($this->company)))->assertSessionHasErrors(['inspection' => 'No pre-trip checklist is active. Contact support.']);
});

it('saves answers as they are given and reports progress as answered ÷ items', function () {
    $inspection = startInspection(Trips::assigned($this->company));

    answer($inspection, [$this->items[0]->id => 'pass'])->assertOk()->assertExactJson(['answered' => 1, 'total' => 3, 'progress' => 33]);
    answer($inspection, [$this->items[0]->id => 'fail', $this->items[2]->id => 'na'])->assertOk()->assertJson(['answered' => 2, 'progress' => 66]);

    expect($inspection->results()->pluck('result', 'template_item_id')->all())->toBe([$this->items[0]->id => 'fail', $this->items[2]->id => 'na']);
    $this->get(route('inspections.show', $inspection))->assertOk()->assertSeeText('2 of 3 items answered')->assertSee('aria-valuenow="66"', false);
    $this->get(route('trips.show', $inspection->trip_id))->assertOk()->assertSee('trip-tabs-tab-inspection', false)->assertSee('aria-valuenow="66"', false);
});

it('saves the whole form without JavaScript', function () {
    $inspection = startInspection(Trips::assigned($this->company));

    $this->put(route('inspections.results.update', $inspection), ['results' => [$this->items[1]->id => ['result' => 'pass', 'note' => 'Bulb replaced']]])
        ->assertRedirect(route('inspections.show', $inspection));

    expect($inspection->results()->sole()->only(['result', 'note']))->toBe(['result' => 'pass', 'note' => 'Bulb replaced']);
});

it('saves the whole form, skipping the empty notes of unanswered items', function () {
    $inspection = startInspection(Trips::assigned($this->company));

    $this->put(route('inspections.results.update', $inspection), ['results' => [
        $this->items[0]->id => ['result' => 'fail', 'note' => 'Cracked lens'],
        $this->items[1]->id => ['note' => ''],
        $this->items[2]->id => ['note' => ''],
    ]])->assertSessionHasNoErrors();

    expect($inspection->results()->pluck('result', 'template_item_id')->all())->toBe([$this->items[0]->id => 'fail']);
});

it('records nothing more once the trip no longer waits to start, but can still be abandoned', function () {
    $trip = Trips::assigned($this->company);
    $inspection = startInspection($trip);
    Trips::service($this->company, fn ($trips) => $trips->cancel($trip->fresh(), 'Load fell through', $this->company->owner));

    answer($inspection, [$this->items[0]->id => 'pass'])->assertUnprocessable()->assertJsonPath('errors.inspection.0', 'The trip is no longer waiting to start. Abandon this inspection.');
    $this->get(route('inspections.show', $inspection))->assertOk()->assertDontSee('data-inspection-autosave', false)->assertSee('Abandon');
    $this->post(route('inspections.abandon', $inspection))->assertSessionHasNoErrors();

    expect($inspection->fresh()->status)->toBe('abandoned');
});

it('accepts only answers to items of its own checklist', function () {
    $inspection = startInspection(Trips::assigned($this->company));
    $foreign = InspectionTemplateFactory::new()->withItems()->create()->items()->first();

    answer($inspection, [$foreign->id => 'pass'])->assertUnprocessable()->assertJsonValidationErrors('results');
    answer($inspection, [$this->items[0]->id => 'broken'])->assertUnprocessable()->assertJsonValidationErrors('results.'.$this->items[0]->id.'.result');
    $this->putJson(route('inspections.results.update', $inspection), ['results' => ['abc' => ['result' => 'pass']]])->assertUnprocessable();
    $this->putJson(route('inspections.results.update', $inspection), ['results' => [$this->items[0]->id => ['note' => 'only a note']]])->assertUnprocessable();

    expect($inspection->results()->exists())->toBeFalse();
});

it('completes only once every required item is answered', function () {
    $inspection = startInspection(Trips::assigned($this->company));
    answer($inspection, [$this->items[0]->id => 'pass']);

    $this->post(route('inspections.complete', $inspection))->assertSessionHasErrors(['inspection' => 'Answer the last required item first.']);
    expect($inspection->fresh()->status)->toBe('in_progress');

    // The third item is optional
    answer($inspection, [$this->items[1]->id => 'na']);
    $this->post(route('inspections.complete', $inspection), ['odometer' => '412345', 'notes' => 'All good'])->assertRedirect(route('trips.show', $inspection->trip_id).'#inspection');

    expect($inspection->fresh()->only(['status', 'result', 'odometer', 'notes']))->toBe(['status' => 'completed', 'result' => 'no_defects', 'odometer' => 412345, 'notes' => 'All good'])
        ->and(AuditLog::where('action', 'inspection.completed')->where('subject_id', $inspection->id)->value('changes'))
        ->toEqual(['trip_id' => $inspection->trip_id, 'result' => 'no_defects', 'failed' => [], 'odometer' => 412345]);
});

it('finds defects when any item fails, and records nothing more once completed', function () {
    $inspection = startInspection(Trips::assigned($this->company));
    answer($inspection, [$this->items[0]->id => 'pass', $this->items[1]->id => 'fail']);

    $this->post(route('inspections.complete', $inspection))->assertSessionHasNoErrors();

    expect($inspection->fresh()->result)->toBe('defects_found')
        ->and(AuditLog::where('action', 'inspection.completed')->value('changes')['failed'])->toBe(['Brake lights']);
    answer($inspection, [$this->items[2]->id => 'pass'])->assertUnprocessable()->assertJsonPath('errors.inspection.0', 'This inspection is already completed.');
    $this->get(route('trips.show', $inspection->trip_id))->assertSee('Defects found')->assertSee('Brake lights');
});

it('keeps the label each result was answered with', function () {
    $inspection = startInspection(Trips::assigned($this->company));
    answer($inspection, [$this->items[0]->id => 'fail']);
    InspectionTemplateItem::query()->whereKey($this->items[0]->id)->update(['label' => 'Headlamps (renamed)']);

    expect(InspectionResult::query()->sole()->item_label)->toBe('Headlights');
    $this->get(route('inspections.show', $inspection))->assertSee('Headlights')->assertDontSee('Headlamps (renamed)');
});

it('abandons an open inspection, audited, and a new one can start', function () {
    $trip = Trips::assigned($this->company);
    $inspection = startInspection($trip);

    $this->post(route('inspections.abandon', $inspection))->assertRedirect(route('trips.show', $trip).'#inspection');

    expect($inspection->fresh()->status)->toBe('abandoned')
        ->and(AuditLog::where('action', 'inspection.abandoned')->where('subject_id', $inspection->id)->exists())->toBeTrue()
        ->and(startInspection($trip)->id)->not->toBe($inspection->id);
    $this->post(route('inspections.abandon', $inspection))->assertSessionHasErrors('inspection');
});

it('refuses to complete or reuse an inspection whose crew no longer matches the trip', function () {
    $company = $this->company;
    $trip = Trips::assigned($company);
    $inspection = startInspection($trip);
    answer($inspection, $this->items->mapWithKeys(fn ($item) => [$item->id => 'pass'])->all());
    Trips::operate($company, fn (TripAssignmentService $a) => $a->assign($trip->fresh(), ['driver_id' => $trip->driver_id, 'vehicle_id' => $trip->vehicle_id, 'trailer_id' => TrailerFactory::new()->forCompany($company)->create()->id], $company->owner));

    $this->get(route('inspections.show', $inspection))->assertSee('changed since this inspection started');
    $this->post(route('inspections.complete', $inspection))->assertSessionHasErrors('inspection');
    $this->post(route('trips.inspections.store', $trip))->assertSessionHasErrors('inspection');
    expect($inspection->fresh()->status)->toBe('in_progress');
});

describe('the pre-trip gate', function () {
    it('needs a completed inspection with no defects of the current crew to start', function () {
        $trip = Trips::assigned($this->company);

        $this->postJson(route('trips.start', $trip))->assertUnprocessable()->assertJsonPath('errors.trip.0', 'Complete the pre-trip inspection before starting the trip.');
        $this->get(route('trips.show', $trip))->assertSee('title="Complete the pre-trip inspection before starting the trip."', false);

        $first = startInspection($trip);
        answer($first, [$this->items[0]->id => 'fail', $this->items[1]->id => 'pass']);
        $this->post(route('inspections.complete', $first));
        $this->postJson(route('trips.start', $trip))->assertUnprocessable()
            ->assertJsonPath('errors.trip.0', 'The last pre-trip inspection found defects. Record a new inspection with no defects before starting the trip.');

        $second = startInspection($trip);
        answer($second, [$this->items[0]->id => 'pass', $this->items[1]->id => 'pass']);
        $this->post(route('inspections.complete', $second));
        $this->post(route('trips.start', $trip))->assertSessionHasNoErrors();

        expect($trip->fresh()->status)->toBe('in_progress');
    });

    it('ignores an inspection of another crew', function () {
        $company = $this->company;
        $trip = Trips::inspected(Trips::assigned($company));
        Trips::operate($company, fn (TripAssignmentService $a) => $a->assign($trip->fresh(), ['driver_id' => $trip->driver_id, 'vehicle_id' => $trip->vehicle_id, 'trailer_id' => null], $company->owner)
            && $a->confirmEquipment($trip->fresh(), $company->owner));

        $this->postJson(route('trips.start', $trip))->assertUnprocessable()->assertJsonPath('errors.trip.0', 'Complete the pre-trip inspection before starting the trip.');
    });

    it('does not apply without the pre_trip_inspections feature (the Company plan)', function () {
        $trip = Trips::assigned($this->company);
        Actors::withPlan($this->company, ['trips', 'drivers', 'fleet', 'document_folders']);

        $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('trip-tabs-tab-inspection', false)->assertDontSee('Complete the pre-trip inspection');
        $this->post(route('trips.inspections.store', $trip))->assertForbidden();
        $this->post(route('trips.start', $trip))->assertSessionHasNoErrors();

        expect($trip->fresh()->status)->toBe('in_progress');
    });
});

describe('attachments for failed items', function () {
    it('files them in the trip folder with the inspection as subject', function () {
        Storage::fake('local');
        $trip = Trips::assigned($this->company);
        $inspection = startInspection($trip);
        answer($inspection, [$this->items[1]->id => 'fail']);

        $this->post(route('inspections.attachments.store', $inspection), ['template_item_id' => $this->items[1]->id, 'files' => [Docs::pdf('lamp.pdf')]])
            ->assertRedirect(route('inspections.show', $inspection).'#item-'.$this->items[1]->id);

        $document = Document::withoutTenancy()->sole();
        expect($document->only(['inspection_id', 'trip_id', 'title']))->toBe(['inspection_id' => $inspection->id, 'trip_id' => null, 'title' => 'Lights · Brake lights'])
            ->and($document->folder_id)->toBe(Docs::systemFolder($trip)->id)
            ->and(Artisan::call('integrity:check'))->toBe(0, Artisan::output());

        $this->get(route('inspections.show', $inspection))->assertSee(route('inspections.attachments.show', [$inspection, $document]));
        $this->get(route('inspections.attachments.show', [$inspection, $document]))->assertOk();
    });

    it('takes files only for a failed item of an open inspection', function () {
        Storage::fake('local');
        $inspection = startInspection(Trips::assigned($this->company));
        answer($inspection, [$this->items[0]->id => 'pass']);

        $this->post(route('inspections.attachments.store', $inspection), ['template_item_id' => $this->items[0]->id, 'files' => [Docs::pdf()]])->assertSessionHasErrors('files');
        $this->post(route('inspections.attachments.store', $inspection), ['template_item_id' => $this->items[0]->id])->assertSessionHasErrors('files');

        expect(Document::withoutTenancy()->exists())->toBeFalse();
    });

    it('serves only a document of that inspection', function () {
        Storage::fake('local');
        $trip = Trips::assigned($this->company);
        $document = DocumentFactory::new()->inFolder(Docs::systemFolder($trip))->create();
        $inspection = startInspection($trip);

        $this->get(route('inspections.attachments.show', [$inspection, $document]))->assertNotFound();
    });
});

it('opens the form within a query budget that does not grow with the items', function () {
    $inspection = startInspection(Trips::assigned($this->company));
    $template = $this->template;

    Perf::assertConstantQueries(function (int $count) use ($inspection, $template) {
        foreach (range(1, $count) as $i) {
            $item = $template->items()->create(['section' => "Section {$i}", 'label' => "Item {$i}", 'sort_order' => 100 + $i, 'is_required' => true]);
            $inspection->results()->create(['template_item_id' => $item->id, 'item_label' => $item->label, 'result' => 'fail']);
        }
    }, fn () => $this->get(route('inspections.show', $inspection))->assertOk());

    Perf::assertMaxQueries(12, fn () => $this->get(route('inspections.show', $inspection))->assertOk()->assertSee('Complete inspection'));
});
