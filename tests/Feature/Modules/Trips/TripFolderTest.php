<?php

use App\Models\Document;
use App\Models\Folder;
use App\Models\Trip;
use App\Services\Documents\FolderBrowser;
use Database\Factories\DocumentFactory;
use Database\Factories\TripFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;

/*
| The trip folder (14 §3, Q8): created with the trip, stored as "Trip #id" and shown as
| "Trip documents"; the trip page shows it as its Documents tab; folders.view alone never
| opens it; folders:ensure-system covers trips made before their folder.
*/

beforeEach(function () {
    Shippers::seedStates();
});

$tripFolders = fn (Trip $trip) => Folder::withoutTenancy()->where('trip_id', $trip->id)->get();

it('creates the trip folder with the trip', function () use ($tripFolders) {
    $trip = Trips::create(Actors::company());

    $folders = $tripFolders($trip);
    expect($folders)->toHaveCount(1)
        ->and($folders[0])->name->toBe("Trip #{$trip->id}")->system_key->toBe('trip')->parent_id->toBeNull()
        ->and($folders[0]->label())->toBe('Trip documents');
});

it('shows the folder as the Documents tab, only with folders.view', function () {
    $company = Actors::company();
    $trip = Trips::create($company);

    $this->actingAs($company->owner)->get(route('trips.show', $trip))->assertOk()
        ->assertSee('trip-tabs-tab-documents', false)->assertSee('Trip documents');
    $this->actingAs(Actors::memberWithPermissions($company, ['trips.view']))->get(route('trips.show', $trip))->assertOk()
        ->assertDontSee('trip-tabs-tab-documents', false);
});

it('refuses the trip folder to a user who may not see trips', function () use ($tripFolders) {
    $company = Actors::company();
    $folder = $tripFolders(Trips::create($company))->first();

    $this->actingAs(Actors::memberWithPermissions($company, ['folders.view', 'drivers.view']))
        ->getJson(route('documents.folders.documents', $folder))->assertForbidden();
    $this->actingAs(Actors::memberWithPermissions($company, ['folders.view', 'trips.view']))
        ->getJson(route('documents.folders.documents', $folder))->assertOk();
});

it('files an upload in the trip folder about the trip', function () use ($tripFolders) {
    $company = Actors::company();
    $trip = Trips::create($company);
    $folder = $tripFolders($trip)->first();
    Storage::fake('local');

    $this->actingAs($company->owner)->post(route('documents.store', $folder), ['files' => [Docs::pdf('bol.pdf')]])->assertSessionHasNoErrors();

    expect(Document::withoutTenancy()->where('folder_id', $folder->id)->value('trip_id'))->toBe($trip->id);
});

it('links a trip folder to the trip page', function () use ($tripFolders) {
    $trip = Trips::create(Actors::company());
    $folder = $tripFolders($trip)->first();

    expect(FolderBrowser::url($folder))->toBe(route('trips.show', [$trip->id, 'folder' => $folder->id]).'#documents');
});

it('creates the missing trip folders once, deleted trips too', function () use ($tripFolders) {
    $company = Actors::company();
    $trip = TripFactory::new()->forCompany($company)->create();
    $deleted = TripFactory::new()->forCompany($company)->cancelled()->create(['deleted_at' => now()]);
    $withFolder = Trips::create($company);

    Artisan::call('folders:ensure-system');
    Artisan::call('folders:ensure-system');

    expect($tripFolders($trip))->toHaveCount(1)
        ->and($tripFolders($deleted))->toHaveCount(1)
        ->and($tripFolders($withFolder))->toHaveCount(1)
        ->and(Artisan::output())->toContain('0 system folder(s) created.');
});

it('keeps a trip document out of another trip\'s folder (integrity query 5)', function () use ($tripFolders) {
    $company = Actors::company();
    $document = DocumentFactory::new()->inFolder($tripFolders(Trips::create($company))->first())->create();

    expect(Artisan::call('integrity:check'))->toBe(0);

    $document->forceFill(['trip_id' => Trips::create($company)->id])->saveQuietly();

    expect(Artisan::call('integrity:check'))->toBe(1)
        ->and(collect(explode("\n", Artisan::output()))->first(fn ($line) => str_starts_with(trim($line), '| 5 ')))->toContain('1 row(s): '.$document->id);
});
