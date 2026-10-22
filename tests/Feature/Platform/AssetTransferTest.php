<?php

use App\Features\Feature;
use App\Models\AssetGroup;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\MaintenanceSchedule;
use App\Models\ServiceDocument;
use App\Models\ServiceLog;
use App\Models\StoredFile;
use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Notifications\AssetTransferredNotification;
use App\Services\Entitlements\FeatureOverrideService;
use App\Services\Platform\AssetTransferService;
use App\Tenancy\TenantContext;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\MaintenanceCategoryFactory;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| PO-08 as a platform operation (04 §7): the target gets copies of the asset, its documents,
| service logs and schedules within its limits; the source keeps its history read-only; copied
| objects are removed again when the transfer fails part-way.
*/

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();

    $this->staff = Actors::platformOwner();
    $this->sourceCompany = Actors::company();
    $this->targetCompany = Actors::company();
});

/** A stored object of $company, written to the fake disk. */
function transferFile(Company $company): StoredFile
{
    $file = StoredFileFactory::new()->forCompany($company)->create();
    Storage::disk('local')->put($file->path, 'content of file '.$file->id);

    return $file;
}

/**
 * A vehicle with a photo, two documents, its own schedule, an all-vehicles schedule, two
 * service logs (one with a category and a document) and an active asset group.
 */
function vehicleWithHistory(Company $company): Vehicle
{
    $vehicle = VehicleFactory::new()->forCompany($company)->create(['photo_file_id' => transferFile($company)->id]);
    $type = DocumentTypeFactory::new()->module('vehicle')->create();
    $own = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create();
    $global = MaintenanceScheduleFactory::new()->create(['vehicle_id' => null, 'company_id' => $company->id]);
    $category = MaintenanceCategoryFactory::new()->create();

    app(TenantContext::class)->run($company->id, function () use ($vehicle, $type, $own, $global, $category, $company) {
        foreach ([1, 2] as $n) {
            VehicleDocument::query()->create([
                'vehicle_id' => $vehicle->id, 'document_type_id' => $type->id,
                'file_id' => transferFile($company)->id, 'expiry_date' => now()->addYear(),
            ]);
        }

        $log = ServiceLogFactory::new()->forVehicle($vehicle)->create(['maintenance_schedule_id' => $own->id]);
        $log->maintenanceCategories()->attach($category->id);
        ServiceDocument::query()->create(['service_log_id' => $log->id, 'file_id' => transferFile($company)->id]);
        ServiceLogFactory::new()->forVehicle($vehicle)->create(['maintenance_schedule_id' => $global->id]);
    });

    AssetGroupFactory::new()->create(['vehicle_id' => $vehicle->id]);

    return $vehicle;
}

function postTransfer(array $overrides = [])
{
    return test()->actingAs(test()->staff, 'platform')
        ->from(route('platform.asset-transfers.create'))
        ->post(route('platform.asset-transfers.store'), $overrides + [
            'asset_type' => 'vehicle',
            'source_company_id' => test()->sourceCompany->id,
            'target_company_id' => test()->targetCompany->id,
            'reason' => 'Sold to the target carrier, ticket 42',
            'confirm' => '1',
        ]);
}

/** @return Builder<Vehicle> */
function vehiclesOf(Company $company)
{
    return Vehicle::withoutTenancy()->withTrashed()->where('company_id', $company->id);
}

it('copies the vehicle and its history into the target within its limits', function () {
    $vehicle = vehicleWithHistory($this->sourceCompany);
    $sourceBytes = $this->sourceCompany->fresh()->storage_bytes;

    postTransfer(['asset_id' => $vehicle->id])->assertRedirect(route('platform.asset-transfers.index'));

    $copy = vehiclesOf($this->targetCompany)->sole();
    $targetFiles = StoredFile::withoutTenancy()->where('company_id', $this->targetCompany->id)->get();

    expect([$copy->unit_no, $copy->vin])->toBe([$vehicle->unit_no, $vehicle->vin])
        ->and($targetFiles)->toHaveCount(4)
        ->and($targetFiles->pluck('id'))->toContain($copy->photo_file_id)
        ->and($targetFiles->every(fn (StoredFile $f) => str_starts_with($f->path, "companies/{$this->targetCompany->id}/vehicles/") && Storage::disk('local')->exists($f->path)))->toBeTrue()
        ->and($this->targetCompany->fresh()->storage_bytes)->toBe((int) $targetFiles->sum('size_bytes'))
        ->and($this->sourceCompany->fresh()->storage_bytes)->toBe($sourceBytes)
        ->and(VehicleDocument::withoutTenancy()->where('vehicle_id', $copy->id)->whereIn('file_id', $targetFiles->pluck('id'))->count())->toBe(2);

    [$schedule, $logs] = app(TenantContext::class)->run($this->targetCompany->id, fn () => [
        MaintenanceSchedule::query()->where('vehicle_id', $copy->id)->sole(),
        ServiceLog::query()->where('vehicle_id', $copy->id)->with(['maintenanceCategories', 'documents'])->orderBy('id')->get(),
    ]);

    expect($schedule->company_id)->toBe($this->targetCompany->id)
        ->and($logs)->toHaveCount(2)
        ->and($logs[0]->maintenance_schedule_id)->toBe($schedule->id)
        ->and($logs[0]->maintenanceCategories)->toHaveCount(1)
        ->and($logs[0]->documents->sole()->file_id)->toBeIn($targetFiles->pluck('id')->all())
        // The all-vehicles schedule belongs to the source company and stays there
        ->and($logs[1]->maintenance_schedule_id)->toBeNull();

    $transfer = AssetTransfer::query()->sole();
    expect($transfer->status)->toBe('completed')
        ->and($transfer->target_vehicle_id)->toBe($copy->id)
        ->and(Arr::sortRecursive($transfer->copied_counts))->toBe(Arr::sortRecursive(['documents' => 2, 'service_logs' => 2, 'schedules' => 1, 'folders' => 0, 'folder_documents' => 0, 'files' => 4]))
        ->and(AuditLog::where('action', 'asset.transferred_out')->where('company_id', $this->sourceCompany->id)->where('actor_type', 'platform_user')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'asset.transferred_in')->where('company_id', $this->targetCompany->id)->where('subject_id', $copy->id)->exists())->toBeTrue();

    Notification::assertSentTo([$this->sourceCompany->owner, $this->targetCompany->owner], AssetTransferredNotification::class);
});

it('retires the vehicle at the source and keeps its history there, read-only', function () {
    $vehicle = vehicleWithHistory($this->sourceCompany);

    postTransfer(['asset_id' => $vehicle->id])->assertSessionHasNoErrors();

    $group = AssetGroup::withoutTenancy()->withTrashed()->where('vehicle_id', $vehicle->id)->sole();
    expect($vehicle->fresh()->trashed())->toBeTrue()
        ->and([$group->status, $group->trashed()])->toBe(['inactive', true])
        ->and(VehicleDocument::withoutTenancy()->where('vehicle_id', $vehicle->id)->count())->toBe(2)
        ->and(ServiceLog::withoutTenancy()->where('vehicle_id', $vehicle->id)->count())->toBe(2)
        ->and(StoredFile::withoutTenancy()->where('company_id', $this->sourceCompany->id)->count())->toBe(4);

    Actors::actingAsTenant($this->sourceCompany->owner);
    $this->postJson(route('admin.vehicle.restore', $vehicle->id))->assertUnprocessable();

    expect($vehicle->fresh()->trashed())->toBeTrue();
});

it('copies a trailer with its documents and takes it out of its groups', function () {
    $trailer = TrailerFactory::new()->forCompany($this->sourceCompany)->create();
    app(TenantContext::class)->run($this->sourceCompany->id, fn () => TrailerDocument::query()->create([
        'trailer_id' => $trailer->id, 'document_type_id' => DocumentTypeFactory::new()->module('trailer')->create()->id,
        'file_id' => transferFile($this->sourceCompany)->id, 'expiry_date' => now()->addYear(),
    ]));
    AssetGroupFactory::new()->forCompany($this->sourceCompany)->create(['trailer_id' => $trailer->id]);

    postTransfer(['asset_type' => 'trailer', 'asset_id' => $trailer->id])->assertSessionHasNoErrors();

    $copy = Trailer::withoutTenancy()->where('company_id', $this->targetCompany->id)->sole();
    expect(TrailerDocument::withoutTenancy()->where('trailer_id', $copy->id)->count())->toBe(1)
        ->and($trailer->fresh()->trashed())->toBeTrue()
        ->and(AssetGroup::withoutTenancy()->where('trailer_id', $trailer->id)->where('status', 'active')->exists())->toBeFalse()
        ->and(Arr::sortRecursive(AssetTransfer::query()->sole()->copied_counts))->toBe(Arr::sortRecursive(['documents' => 1, 'service_logs' => 0, 'schedules' => 0, 'folders' => 0, 'folder_documents' => 0, 'files' => 1]));
});

describe('refusals', function () {
    it('refuses when the target is at its vehicle limit', function () {
        $vehicle = vehicleWithHistory($this->sourceCompany);
        VehicleFactory::new()->forCompany($this->targetCompany)->create();
        app(FeatureOverrideService::class)->grant($this->staff, $this->targetCompany, Feature::MaxVehicles, null, 1, 'test');

        postTransfer(['asset_id' => $vehicle->id])->assertSessionHasErrors('limit');

        expect(vehiclesOf($this->targetCompany)->count())->toBe(1)
            ->and($vehicle->fresh()->trashed())->toBeFalse()
            ->and(AssetTransfer::query()->exists())->toBeFalse();
    });

    it('refuses when the files would pass the target storage limit', function () {
        $vehicle = vehicleWithHistory($this->sourceCompany);
        app(FeatureOverrideService::class)->grant($this->staff, $this->targetCompany, Feature::StorageGb, null, 0, 'test');

        postTransfer(['asset_id' => $vehicle->id])->assertSessionHasErrors('limit');

        expect(vehiclesOf($this->targetCompany)->exists())->toBeFalse()
            ->and(Storage::disk('local')->allFiles("companies/{$this->targetCompany->id}"))->toBe([]);
    });

    it('refuses a unit number or VIN the target already uses, retired assets included', function () {
        $vehicle = vehicleWithHistory($this->sourceCompany);
        VehicleFactory::new()->forCompany($this->targetCompany)->create(['vin' => $vehicle->vin])->delete();

        postTransfer(['asset_id' => $vehicle->id])->assertSessionHasErrors('asset_id');

        expect($vehicle->fresh()->trashed())->toBeFalse();
    });

    it('refuses the same company, an asset of another company, and a missing confirmation', function () {
        $vehicle = vehicleWithHistory($this->sourceCompany);
        $other = VehicleFactory::new()->forCompany($this->targetCompany)->create();

        postTransfer(['asset_id' => $vehicle->id, 'target_company_id' => $this->sourceCompany->id])->assertSessionHasErrors('target_company_id');
        postTransfer(['asset_id' => $other->id])->assertSessionHasErrors('asset_id');
        postTransfer(['asset_id' => $vehicle->id, 'confirm' => null])->assertSessionHasErrors('confirm');

        expect(AssetTransfer::query()->exists())->toBeFalse();
    });
});

it('rolls back rows and copied objects when the copy fails part-way, and records the failure', function () {
    $vehicle = vehicleWithHistory($this->sourceCompany);
    // The last service document's object is gone, so copying stops after the other files
    $lastFile = StoredFile::withoutTenancy()->where('company_id', $this->sourceCompany->id)->orderByDesc('id')->first();
    Storage::disk('local')->delete($lastFile->path);
    $targetBytes = $this->targetCompany->fresh()->storage_bytes;

    expect(fn () => app(AssetTransferService::class)->transfer($this->staff, 'vehicle', $vehicle->id, $this->sourceCompany, $this->targetCompany, 'test'))
        ->toThrow(RuntimeException::class);

    expect(vehiclesOf($this->targetCompany)->exists())->toBeFalse()
        ->and(StoredFile::withoutTenancy()->where('company_id', $this->targetCompany->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles("companies/{$this->targetCompany->id}"))->toBe([])
        ->and($this->targetCompany->fresh()->storage_bytes)->toBe($targetBytes)
        ->and($vehicle->fresh()->trashed())->toBeFalse()
        ->and(AssetTransfer::query()->sole()->status)->toBe('failed')
        ->and(AuditLog::where('action', 'asset.transfer_failed')->exists())->toBeTrue();
});

it('is only for staff with platform.assets.transfer', function () {
    $vehicle = vehicleWithHistory($this->sourceCompany);

    $this->actingAs(Actors::platformSupport(), 'platform')
        ->post(route('platform.asset-transfers.store'), ['asset_type' => 'vehicle', 'asset_id' => $vehicle->id, 'source_company_id' => $this->sourceCompany->id, 'target_company_id' => $this->targetCompany->id, 'reason' => 'x', 'confirm' => '1'])
        ->assertForbidden();

    expect($vehicle->fresh()->trashed())->toBeFalse();
});

it('sends tenant users to the platform login', function () {
    $this->actingAs($this->sourceCompany->owner)
        ->post(route('platform.asset-transfers.store'), [])
        ->assertRedirect(route('platform.login'));
});

it('lists only the chosen company assets in the picker, by unit number and VIN', function () {
    $vehicle = VehicleFactory::new()->forCompany($this->sourceCompany)->create();
    VehicleFactory::new()->forCompany($this->targetCompany)->create();

    $this->actingAs($this->staff, 'platform')
        ->getJson(route('platform.asset-transfers.assets', ['company_id' => $this->sourceCompany->id, 'asset_type' => 'vehicle']))
        ->assertOk()
        ->assertExactJson(['data' => [['id' => $vehicle->id, 'unit_no' => $vehicle->unit_no, 'vin' => $vehicle->vin]]]);
});
