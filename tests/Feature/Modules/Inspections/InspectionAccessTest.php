<?php

use App\Authorization\RoleTemplates;
use App\Features\Feature;
use App\Models\Company;
use App\Models\Inspection;
use Database\Factories\DocumentFactory;
use Database\Factories\InspectionFactory;
use Database\Factories\InspectionTemplateFactory;
use Database\Factories\TripFactory;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Support\Actors;

/*
| Who reaches pre-trip inspections (P2-4): inspections.view to read, inspections.manage (write,
| Dispatcher template) to record; the pre_trip_inspections feature on every route; nothing written
| for a read-only company.
*/

beforeEach(function () {
    InspectionTemplateFactory::new()->active()->withItems()->create();
});

function openInspection(Company $company): Inspection
{
    return InspectionFactory::new()->forTrip(TripFactory::new()->forCompany($company)->assigned()->create())->create();
}

/**
 * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
 */
function inspectionWriteRoutes(Inspection $inspection): array
{
    $item = $inspection->template()->first()->items()->first();

    return [
        ['POST', route('trips.inspections.store', $inspection->trip_id), []],
        ['PUT', route('inspections.results.update', $inspection), ['results' => [$item->id => ['result' => 'pass']]]],
        ['POST', route('inspections.complete', $inspection), []],
        ['POST', route('inspections.abandon', $inspection), []],
        ['POST', route('inspections.attachments.store', $inspection), ['template_item_id' => $item->id]],
    ];
}

it('gives the Dispatcher template inspections.view and inspections.manage', function () {
    expect(RoleTemplates::permissionsFor('Dispatcher'))->toContain('inspections.view', 'inspections.manage')
        ->not->toContain('inspections.perform.own');
});

it('reads with inspections.view only, without any way to record', function () {
    $company = Actors::company();
    $inspection = openInspection($company);
    $this->actingAs(Actors::memberWithPermissions($company, ['trips.view', 'inspections.view']));

    $this->get(route('trips.show', $inspection->trip_id))->assertOk()->assertSee('trip-tabs-tab-inspection', false)->assertDontSee('Start inspection');
    $this->get(route('inspections.show', $inspection))->assertOk()->assertDontSee('Complete inspection')->assertDontSee('data-inspection-autosave', false);
    foreach (inspectionWriteRoutes($inspection) as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertForbidden();
    }
    expect($inspection->fresh()->status)->toBe('in_progress');
});

it('hides the tab without inspections.view', function () {
    $company = Actors::company();
    $inspection = openInspection($company);
    $this->actingAs(Actors::memberWithPermissions($company, ['trips.view']));

    $this->get(route('trips.show', $inspection->trip_id))->assertOk()->assertDontSee('trip-tabs-tab-inspection', false);
    $this->get(route('inspections.show', $inspection))->assertForbidden();
});

it('refuses every write for a read-only company but keeps the pages', function () {
    $company = Actors::readOnly(Actors::company());
    $inspection = openInspection($company);
    $this->actingAs($company->owner);

    $this->get(route('inspections.show', $inspection))->assertOk()->assertDontSee('Complete inspection');
    foreach (inspectionWriteRoutes($inspection) as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertForbidden();
    }
    expect($inspection->fresh()->status)->toBe('in_progress');
});

it('refuses every route without the pre_trip_inspections feature', function () {
    $company = Actors::company();
    $inspection = openInspection($company);
    Actors::withPlan($company, collect(Feature::cases())->reject(fn (Feature $f) => $f === Feature::PreTripInspections || $f->isLimit())->map->value->all());
    $this->actingAs($company->owner);

    $this->get(route('inspections.show', $inspection))->assertForbidden();
    foreach (inspectionWriteRoutes($inspection) as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertForbidden();
    }
});

it('serves an attachment only to users who may see the trip folder', function () {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->assigned()->create();
    $inspection = InspectionFactory::new()->forTrip($trip)->create();
    $document = DocumentFactory::new()->inFolder(Docs::systemFolder($trip))->create(['trip_id' => null, 'inspection_id' => $inspection->id]);

    $this->actingAs(Actors::memberWithPermissions($company, ['inspections.view']))
        ->get(route('inspections.attachments.show', [$inspection, $document]))->assertForbidden();
});
