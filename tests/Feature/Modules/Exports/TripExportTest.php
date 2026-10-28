<?php

use App\Features\Feature;
use App\Jobs\BuildExport;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Export;
use App\Models\StoredFile;
use App\Models\Trip;
use Database\Factories\DocumentFactory;
use Database\Factories\ExportFactory;
use Database\Factories\FuelPurchaseFactory;
use Database\Factories\InspectionFactory;
use Database\Factories\TripEventFactory;
use Database\Factories\TripFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Exports\Exports;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| The trip ZIP (req 2.5, Q8, P2-6): what the trip page shows (its folder, with the event, fuel and
| inspection files and the original images), laid out as Trips/<number> (#id)/…; exports.create,
| trips.view and folders.view; the zip_export and trips features; read-only companies may export
| (Q2). The company ZIP has Trips/ when the requester sees trips.
*/

beforeEach(function () {
    Storage::fake('local');
});

/**
 * A trip with a document of each kind in its folder (and one trashed), plus a voided event.
 */
function exportTrip(Company $company, int $number = 1042): Trip
{
    $trip = TripFactory::new()->forCompany($company)->inProgress()->create(['trip_number' => $number]);
    $folder = Docs::systemFolder($trip);
    $file = fn (string $extension = 'pdf', array $attributes = []) => Files::stored($company->id, $extension === 'pdf' ? '%PDF-1.4 test' : $extension, $extension, attributes: $attributes);

    DocumentFactory::new()->inFolder($folder)->withFile($file())->create(['title' => 'Bill of lading']);
    $image = $file('jpg');
    DocumentFactory::new()->inFolder($folder)->withFile($file(attributes: ['converted_from_file_id' => $image->id]))
        ->create(['title' => 'Seal photo', 'original_file_id' => $image->id]);
    DocumentFactory::new()->inFolder(Docs::childOf($folder, ['name' => 'Receipts']))->withFile($file())->create(['title' => 'Lumper']);
    DocumentFactory::new()->inFolder($folder)->withFile($file())->create(['title' => 'Trashed'])->delete();

    $police = TripEventFactory::new()->forTrip($trip)->ofType('police_stop')->voided()->create();
    DocumentFactory::new()->inFolder($folder)->withFile($file())->create(['title' => 'Police ticket', 'trip_id' => null, 'trip_event_id' => $police->id]);
    $fuel = TripEventFactory::new()->forTrip($trip)->ofType('fuel')->create();
    $purchase = FuelPurchaseFactory::new()->forEvent($fuel)->confirmed()->create();
    DocumentFactory::new()->inFolder($folder)->withFile($file())->create(['title' => 'Fuel receipt', 'trip_id' => null, 'fuel_purchase_id' => $purchase->id]);
    $inspection = InspectionFactory::new()->forTrip($trip)->completed('defects_found')->create();
    DocumentFactory::new()->inFolder($folder)->withFile($file())->create(['title' => 'Brakes', 'trip_id' => null, 'inspection_id' => $inspection->id]);

    return $trip;
}

/**
 * @return list<string>
 */
function exportTripEntries(Trip $trip): array
{
    $base = "Trips/{$trip->trip_number} (#{$trip->id})/Trip documents";

    return [
        "{$base}/Bill of lading.pdf",
        "{$base}/Brakes.pdf",
        "{$base}/Fuel receipt.pdf",
        "{$base}/Police ticket.pdf",
        "{$base}/Receipts/Lumper.pdf",
        "{$base}/Seal photo (original).jpg",
        "{$base}/Seal photo.pdf",
    ];
}

it('builds a trip ZIP of what the trip page shows, and nothing of another trip or company', function () {
    $company = Actors::company();
    $trip = exportTrip($company);
    exportTrip($company, 1043);
    exportTrip(Actors::company());

    $this->actingAs($company->owner)->post(route('trips.export', $trip))->assertRedirect(route('exports.index'));

    $export = Exports::latest($company);
    expect($export->only(['type', 'status', 'parameters']))->toEqual(['type' => 'zip_trip', 'status' => 'ready', 'parameters' => ['trip_id' => $trip->id]])
        ->and(Exports::entries($export))->toBe(exportTripEntries($trip))
        ->and(StoredFile::withoutTenancy()->find($export->file_id)->original_name)->toBe('Trip files - 1042 - '.now()->toDateString().'.zip')
        ->and(AuditLog::query()->where('company_id', $company->id)->where('action', 'export.requested')->where('subject_id', $export->id)->value('changes'))
        ->toMatchArray(['type' => 'zip_trip', 'trip_id' => $trip->id]);
    expect(Artisan::call('integrity:check'))->toBe(0, Artisan::output());
});

it('keeps cancelled trips exportable and returns the pending export of the same trip', function () {
    Queue::fake();
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->cancelled()->create();
    $this->actingAs($company->owner);

    $this->post(route('trips.export', $trip))->assertRedirect(route('exports.index'));
    $this->post(route('trips.export', $trip))->assertRedirect(route('exports.index'));

    expect(Export::withoutTenancy()->where('company_id', $company->id)->count())->toBe(1);
    Queue::assertPushed(BuildExport::class, 1);
});

it('shows the Download ZIP button on the trip page to users who may export it', function () {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->create();

    $this->actingAs($company->owner)->get(route('trips.show', $trip))->assertOk()->assertSee(route('trips.export', $trip));
    $this->actingAs(Actors::dispatcher($company))->get(route('trips.show', $trip))->assertOk()->assertDontSee(route('trips.export', $trip));
});

it('refuses users without exports.create, trips.view or folders.view', function (array $permissions) {
    Queue::fake();
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->create();

    $this->actingAs(Actors::memberWithPermissions($company, $permissions))->post(route('trips.export', $trip))->assertForbidden();

    expect(Export::withoutTenancy()->count())->toBe(0);
})->with([
    'no exports.create' => [['trips.view', 'folders.view', 'exports.download']],
    'no trips.view' => [['exports.create', 'exports.download', 'folders.view', 'drivers.view']],
    'no folders.view' => [['exports.create', 'exports.download', 'trips.view']],
]);

it('lets a user without folders.view export when the company has no folders', function () {
    Queue::fake();
    $company = Actors::withPlan(Actors::company(), ['trips', 'zip_export']);
    $trip = TripFactory::new()->forCompany($company)->create();

    $this->actingAs(Actors::memberWithPermissions($company, ['exports.create', 'trips.view']))->post(route('trips.export', $trip))
        ->assertRedirect(route('exports.index'));
});

it('refuses the export without the zip_export or the trips feature', function (Feature $missing) {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->create();
    Actors::withPlan($company, collect(Feature::cases())->reject(fn (Feature $f) => $f === $missing || $f->isLimit())->map->value->all());

    $this->actingAs($company->owner)->post(route('trips.export', $trip))->assertForbidden();
    if ($missing === Feature::ZipExport) {
        $this->get(route('trips.show', $trip))->assertOk()->assertDontSee(route('trips.export', $trip));
    }
})->with([Feature::ZipExport, Feature::Trips]);

it('answers 404 for a deleted trip and fails the export of a trip deleted after the request', function () {
    $company = Actors::company();
    $deleted = TripFactory::new()->forCompany($company)->draft()->create();
    $deleted->delete();
    $this->actingAs($company->owner)->post(route('trips.export', $deleted))->assertNotFound();

    $trip = TripFactory::new()->forCompany($company)->draft()->create();
    $export = ExportFactory::new()->forTrip($trip)->create();
    $trip->delete();
    BuildExport::dispatchSync($company->id, $export->id);

    expect($export->fresh()->only(['status', 'error']))->toBe(['status' => 'failed', 'error' => 'The trip no longer exists.']);
});

it('lets a read-only company export a trip (Q2)', function () {
    $company = Actors::company();
    $trip = exportTrip($company);
    Actors::readOnly($company);

    $this->actingAs($company->owner)->post(route('trips.export', $trip))->assertRedirect(route('exports.index'));

    expect(Exports::latest($company)->status)->toBe('ready')
        ->and(Exports::entries(Exports::latest($company)))->toBe(exportTripEntries($trip));
});

it('downloads a trip ZIP only with trips.view, and shows its number only with trips.view', function () {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->create(['trip_number' => 77]);
    $export = ExportFactory::new()->forTrip($trip)->ready(Exports::zipFile($company))->create();

    $this->actingAs(Actors::officeUser($company))->get(route('exports.download', $export))->assertForbidden();
    $this->actingAs($company->owner)->get(route('exports.download', $export))->assertOk();

    $rows = $this->getJson(route('exports.data').'?draw=1&start=0&length=10')->assertOk()->json('data');
    expect($rows[0]['scope'])->toBe('Trip #77');
    $officeRows = $this->actingAs(Actors::officeUser($company))->getJson(route('exports.data').'?draw=1&start=0&length=10')->assertOk()->json('data');
    expect($officeRows[0]['scope'])->toBe('Trip');
    expect(AuditLog::query()->where('action', 'export.downloaded')->where('subject_id', $export->id)->value('changes'))->toMatchArray(['trip_id' => $trip->id]);
});

it('adds Trips/ to the company ZIP only for a requester who sees trips', function () {
    $company = Actors::company();
    $trip = exportTrip($company);
    $office = Actors::officeUser($company);

    $this->actingAs($company->owner)->post(route('exports.store'), ['type' => 'zip_company']);
    $withTrips = Exports::latest($company);
    $this->actingAs($office)->post(route('exports.store'), ['type' => 'zip_company']);
    $withoutTrips = Exports::latest($company);

    expect($withTrips->parameters)->toBe(['trips' => 1])
        ->and(array_values(array_filter(Exports::entries($withTrips), fn ($name) => str_starts_with($name, 'Trips/'))))->toBe(exportTripEntries($trip))
        ->and($withoutTrips->parameters)->toBe([])
        ->and(array_filter(Exports::entries($withoutTrips), fn ($name) => str_starts_with($name, 'Trips/')))->toBe([]);

    $this->actingAs($office)->get(route('exports.download', $withTrips))->assertForbidden();
    $this->actingAs($office)->get(route('exports.download', $withoutTrips))->assertOk();
});
