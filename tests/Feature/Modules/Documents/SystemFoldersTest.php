<?php

use App\Models\Document;
use App\Models\Driver;
use App\Models\Folder;
use App\Models\ServiceLog;
use App\Models\StoredFile;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentFactory;
use Database\Factories\DriverFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| Req 2.1 (decided Q8): one standard folder per driver (DQ file), vehicle and trailer, created in
| the transaction that creates the entity; folders:ensure-system covers the rows that came before.
*/

function systemFolderOf(Driver|Vehicle|Trailer $entity): ?Folder
{
    return Folder::withoutTenancy()->where($entity->getForeignKey(), $entity->id)->whereNotNull('system_key')->first();
}

beforeEach(function () {
    $this->owner = Actors::companyOwner();
    $this->company = Actors::companyOf($this->owner);
});

it('creates the DQ file with a driver added by staff', function () {
    $this->actingAs($this->owner)->post(route('admin.driver.store'), DriverAdmin::payload())->assertSessionHasNoErrors();

    $driver = Driver::withoutTenancy()->where('company_id', $this->company->id)->sole();
    expect(systemFolderOf($driver))->not->toBeNull()
        ->system_key->toBe('dq_file')
        ->name->toBe("DQ file #{$driver->id}")
        ->parent_id->toBeNull();
});

it('creates the DQ file of a public applicant when it is hired, once', function () {
    Mail::fake();
    $applicant = DriverFactory::new()->forCompany($this->company)->application('submitted')->create();
    expect(systemFolderOf($applicant))->toBeNull();

    $this->actingAs($this->owner)->postJson(route('admin.driver.hire-status', $applicant->id), [
        'action' => 'hire', 'hire_date' => now()->toDateString(), 'hazmat' => 'no', 'lcv_certificate' => 'no',
    ])->assertOk();

    expect(Folder::withoutTenancy()->where('driver_id', $applicant->id)->count())->toBe(1);
});

it('creates the folder with a new vehicle or trailer', function (string $kind) {
    $this->actingAs($this->owner)->withHeaders(FleetAssets::ajax())
        ->post(route("admin.$kind.store"), FleetAssets::payload($kind))->assertOk();

    $asset = FleetAssets::model($kind)::withoutTenancy()->where('company_id', $this->company->id)->sole();
    expect(systemFolderOf($asset)?->system_key)->toBe($kind);
})->with(['vehicle', 'trailer']);

it('creates the missing folders once with folders:ensure-system', function () {
    $hired = DriverFactory::new()->forCompany($this->company)->create();
    $staffApplicant = DriverFactory::new()->forCompany($this->company)->status('pending')->create();
    $publicApplicant = DriverFactory::new()->forCompany($this->company)->application('submitted')->create();
    $retired = VehicleFactory::new()->forCompany($this->company)->create();
    $retired->delete();
    $trailer = TrailerFactory::new()->forCompany($this->company)->create();
    $existing = Docs::systemFolder(VehicleFactory::new()->forCompany($this->company)->create());

    $this->artisan('folders:ensure-system')->expectsOutput('4 system folder(s) created.')->assertSuccessful();
    $this->artisan('folders:ensure-system')->expectsOutput('0 system folder(s) created.')->assertSuccessful();

    expect(Folder::withoutTenancy()->count())->toBe(5)
        ->and([systemFolderOf($hired), systemFolderOf($staffApplicant), systemFolderOf($retired), systemFolderOf($trailer)])->each->not->toBeNull()
        ->and(systemFolderOf($publicApplicant))->toBeNull()
        ->and($existing->fresh()->trashed())->toBeFalse();
});

it('copies the vehicle folder tree and its documents with an Asset Transfer', function () {
    Storage::fake('local');
    Notification::fake();
    $target = Actors::company();
    $vehicle = VehicleFactory::new()->forCompany($this->company)->create();
    $root = Docs::systemFolder($vehicle);
    $permits = Docs::childOf($root, ['name' => 'Permits']);
    $file = StoredFileFactory::new()->forCompany($this->company)->create();
    Storage::disk('local')->put($file->path, 'permit');
    DocumentFactory::new()->inFolder($permits)->withFile($file)->create(['title' => 'IRP cab card']);
    DocumentFactory::new()->inFolder($permits)->create()->delete();

    $this->actingAs(Actors::platformOwner(), 'platform')->post(route('platform.asset-transfers.store'), [
        'asset_type' => 'vehicle', 'asset_id' => $vehicle->id, 'source_company_id' => $this->company->id,
        'target_company_id' => $target->id, 'reason' => 'Sold to the target carrier', 'confirm' => '1',
    ])->assertRedirect(route('platform.asset-transfers.index'));

    [$folders, $document] = app(TenantContext::class)->run($target->id, fn () => [
        Folder::query()->orderBy('id')->get(),
        Document::query()->with('file')->sole(),
    ]);
    $copy = Vehicle::withoutTenancy()->where('company_id', $target->id)->sole();

    expect($folders->pluck('name')->all())->toBe(["Vehicle #{$copy->id}", 'Permits'])
        ->and($folders->pluck('vehicle_id')->unique()->all())->toBe([$copy->id])
        ->and($folders[1]->parent_id)->toBe($folders[0]->id)
        ->and($document)->title->toBe('IRP cab card')->folder_id->toBe($folders[1]->id)->vehicle_id->toBe($copy->id)
        ->and($document->file->company_id)->toBe($target->id)
        ->and(str_starts_with($document->file->path, "companies/{$target->id}/documents/"))->toBeTrue()
        ->and(Storage::disk('local')->get($document->file->path))->toBe('permit')
        ->and(StoredFile::withoutTenancy()->where('company_id', $target->id)->count())->toBe(1)
        // The source keeps its folders and documents
        ->and(Folder::withoutTenancy()->where('vehicle_id', $vehicle->id)->count())->toBe(2);
});

it('keeps a vehicle-folder document about a service log on the copied log', function () {
    Storage::fake('local');
    Notification::fake();
    $target = Actors::company();
    $vehicle = VehicleFactory::new()->forCompany($this->company)->create();
    $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();
    $file = StoredFileFactory::new()->forCompany($this->company)->create();
    Storage::disk('local')->put($file->path, 'invoice');
    DocumentFactory::new()->inFolder(Docs::systemFolder($vehicle))->withFile($file)->create(['vehicle_id' => null, 'service_log_id' => $log->id]);

    $this->actingAs(Actors::platformOwner(), 'platform')->post(route('platform.asset-transfers.store'), [
        'asset_type' => 'vehicle', 'asset_id' => $vehicle->id, 'source_company_id' => $this->company->id,
        'target_company_id' => $target->id, 'reason' => 'Sold to the target carrier', 'confirm' => '1',
    ])->assertSessionHasNoErrors();

    $document = Document::withoutTenancy()->where('company_id', $target->id)->sole();
    $copiedLog = ServiceLog::withoutTenancy()->where('company_id', $target->id)->sole();
    expect($document->service_log_id)->toBe($copiedLog->id)->and($document->vehicle_id)->toBeNull();
});
