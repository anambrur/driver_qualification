<?php

use App\Jobs\BuildExport;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Export;
use App\Models\ServiceDocument;
use App\Models\StoredFile;
use App\Models\TrailerDocument;
use App\Models\VehicleDocument;
use App\Notifications\ExportFinishedNotification;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverComplianceDocumentFactory;
use Database\Factories\DriverFactory;
use Database\Factories\ExportFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Exports\Exports;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| BuildExport (req 2.5, Q8): everything attached to the entity, in the documented layout, read
| from the company's own rows only; ready for 7 days outside the storage counter; failures land
| on the row.
*/

beforeEach(function () {
    Storage::fake('local');
});

/**
 * A driver "Doe, Jane" with a typed document, application files (the medical card is the
 * compliance document's file, F17), a photo and DQ-file documents.
 */
function exportDriver(Company $company, array $attributes = ['first_name' => 'Jane', 'last_name' => 'Doe']): Driver
{
    [$driver, $dqFile] = Docs::dqFile($company);
    $driver->update($attributes + ['photo_file_id' => Files::stored($company->id, 'jpg', 'jpg')->id]);
    $type = DocumentType::query()->firstOrCreate(['module' => 'driver', 'name' => 'MVR'], DocumentTypeFactory::new()->module('driver')->raw(['name' => 'MVR']));
    $mvr = DriverComplianceDocumentFactory::new()->forDriver($driver, $type)
        ->create(['file_id' => Files::stored($company->id)->id, 'expiry_date' => '2027-01-15']);
    DriverDocument::query()->create([
        'driver_id' => $driver->id,
        'license_front_file_id' => Files::stored($company->id, 'png', 'png')->id,
        'medical_card_file_id' => $mvr->file_id,
    ]);

    // A converted photo: the PDF links its image (integrity query 13)
    $image = Files::stored($company->id, 'jpg', 'jpg');
    DocumentFactory::new()->inFolder($dqFile)->withFile(Files::stored($company->id, attributes: ['converted_from_file_id' => $image->id]))
        ->create(['title' => 'Road test', 'original_file_id' => $image->id]);
    DocumentFactory::new()->inFolder($dqFile)->withFile(Files::stored($company->id))->create(['title' => 'Road test']);
    DocumentFactory::new()->inFolder(Docs::childOf($dqFile, ['name' => 'Medical']))->withFile(Files::stored($company->id))
        ->create(['title' => 'Physical: 2026/10']);
    DocumentFactory::new()->inFolder($dqFile)->withFile(Files::stored($company->id))->create(['title' => 'Trashed'])->delete();

    return $driver->fresh();
}

/**
 * @return list<string>
 */
function exportDriverEntries(Driver $driver, string $name = 'Doe, Jane'): array
{
    $base = "Drivers/{$name} (#{$driver->id})";

    return [
        "{$base}/Application/License front.png",
        "{$base}/DQ file/Medical/Physical- 2026-10.pdf",
        "{$base}/DQ file/Road test (2).pdf",
        "{$base}/DQ file/Road test (original).jpg",
        "{$base}/DQ file/Road test.pdf",
        "{$base}/MVR/MVR 2027-01-15.pdf",
        "{$base}/Photo.jpg",
    ];
}

it('builds a driver ZIP with everything attached to the driver', function () {
    Notification::fake();
    $company = Actors::company();
    $driver = exportDriver($company);
    $bytes = $company->fresh()->storage_bytes;

    $this->actingAs($company->owner)->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id]);

    $export = Exports::latest($company);
    expect($export->status)->toBe('ready')
        ->and($export->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue()
        ->and(Exports::entries($export))->toBe(exportDriverEntries($driver));

    $zip = StoredFile::withoutTenancy()->find($export->file_id);
    expect($zip->path)->toStartWith("companies/{$company->id}/exports/")
        ->and($zip->original_name)->toBe("DQ file - Doe, Jane (#{$driver->id}) - ".now()->toDateString().'.zip')
        ->and($company->fresh()->storage_bytes)->toBe($bytes);
    Notification::assertSentTo($company->owner, ExportFinishedNotification::class, fn ($n, $channels) => $channels === ['database', 'mail']);
    expect(Artisan::call('integrity:check'))->toBe(0, Artisan::output());
});

it('builds a company ZIP of every driver, vehicle, trailer and company folder, and nothing of another company', function () {
    $company = Actors::company();
    $active = exportDriver($company);
    $terminated = DriverFactory::new()->forCompany($company)->employment('terminated')
        ->create(['first_name' => 'Tom', 'last_name' => 'Gone', 'photo_file_id' => Files::stored($company->id, 'jpg', 'jpg')->id]);
    DriverFactory::new()->forCompany($company)->publicDraft()->create(['photo_file_id' => Files::stored($company->id, 'jpg', 'jpg')->id]);
    $deleted = DriverFactory::new()->forCompany($company)->create(['photo_file_id' => Files::stored($company->id, 'jpg', 'jpg')->id]);
    $deleted->delete();

    $vehicle = VehicleFactory::new()->forCompany($company)->create(['unit_no' => 'T/100', 'photo_file_id' => Files::stored($company->id, 'png', 'png')->id]);
    $trailer = TrailerFactory::new()->forCompany($company)->create(['unit_no' => 'TR-7']);
    $log = ServiceLogFactory::new()->forVehicle($vehicle)->create(['service_date' => '2026-09-01']);
    app(TenantContext::class)->run($company->id, function () use ($company, $vehicle, $trailer, $log) {
        VehicleDocument::create(['vehicle_id' => $vehicle->id, 'document_type_id' => DocumentTypeFactory::new()->module('vehicle')->create(['name' => 'Registration'])->id,
            'file_id' => Files::stored($company->id)->id, 'expiry_date' => '2027-03-01']);
        TrailerDocument::create(['trailer_id' => $trailer->id, 'document_type_id' => DocumentTypeFactory::new()->module('trailer')->create(['name' => 'Inspection'])->id,
            'file_id' => Files::stored($company->id)->id, 'file_date' => '2026-05-02', 'expiry_date' => '2027-05-02']);
        ServiceDocument::create(['service_log_id' => $log->id, 'file_id' => Files::stored($company->id, attributes: ['original_name' => 'invoice.pdf'])->id]);
    });
    DocumentFactory::new()->inFolder(Docs::systemFolder($vehicle))->withFile(Files::stored($company->id))->create(['title' => 'Lease']);
    DocumentFactory::new()->inFolder(Docs::companyFolder($company, ['name' => 'Policies']))->withFile(Files::stored($company->id))->create(['title' => 'Handbook']);

    $other = Actors::company();
    exportDriver($other, ['first_name' => 'Other', 'last_name' => 'Company']);
    DocumentFactory::new()->inFolder(Docs::companyFolder($other, ['name' => 'Secret']))->withFile(Files::stored($other->id))->create(['title' => 'Theirs']);

    $this->actingAs($company->owner)->post(route('exports.store'), ['type' => 'zip_company']);

    $export = Exports::latest($company);
    $expected = [
        ...exportDriverEntries($active),
        'Company folders/Policies/Handbook.pdf',
        "Drivers/Gone, Tom (#{$terminated->id})/Photo.jpg",
        "Trailers/TR-7 (#{$trailer->id})/Inspection/Inspection 2027-05-02.pdf",
        "Vehicles/T-100 (#{$vehicle->id})/Photo.png",
        "Vehicles/T-100 (#{$vehicle->id})/Registration/Registration 2027-03-01.pdf",
        "Vehicles/T-100 (#{$vehicle->id})/Service logs/2026-09-01 (#{$log->id})/invoice.pdf",
        "Vehicles/T-100 (#{$vehicle->id})/Vehicle documents/Lease.pdf",
    ];
    sort($expected);

    expect($export->status)->toBe('ready')
        ->and(Exports::entries($export))->toBe($expected);
});

it('lists files missing from storage instead of failing', function () {
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $driver->update(['photo_file_id' => StoredFileFactory::new()->forCompany($company)->create()->id]);

    $this->actingAs($company->owner)->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id]);

    $export = Exports::latest($company);
    expect($export->status)->toBe('ready')
        ->and(Exports::entries($export))->toBe(['Missing files.txt']);
});

it('fails an export above the size cap with a message and leaves no temporary files', function () {
    Notification::fake();
    config(['exports.max_bytes' => 10]);
    $company = Actors::company();
    $driver = exportDriver($company);
    $temporary = fn () => count(glob(sys_get_temp_dir().'/dq-zip-*') ?: []);
    $before = $temporary();

    $this->actingAs($company->owner)->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id]);

    $export = Exports::latest($company);
    expect($export->status)->toBe('failed')
        ->and($export->error)->toContain('is larger than')
        ->and($export->file_id)->toBeNull()
        ->and($temporary())->toBe($before)
        ->and(StoredFile::withoutTenancy()->where('path', 'like', "companies/{$company->id}/exports/%")->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'export.failed')->where('subject_id', $export->id)->exists())->toBeTrue();
    Notification::assertSentTo($company->owner, ExportFinishedNotification::class);
});

it('fails the export when the driver was deleted after the request', function () {
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->create();
    $export = ExportFactory::new()->forDriver($driver)->create();
    $driver->delete();

    BuildExport::dispatchSync($company->id, $export->id);

    expect($export->fresh()->only(['status', 'error']))->toBe(['status' => 'failed', 'error' => 'The driver no longer exists.']);
});

it('builds each export once', function () {
    $company = Actors::company();
    $export = ExportFactory::new()->state(['company_id' => $company->id, 'status' => 'running', 'started_at' => now()->subMinute()])->create();

    BuildExport::dispatchSync($company->id, $export->id);

    expect($export->fresh()->status)->toBe('running');
});

it('fails a redelivered build whose worker died', function () {
    $company = Actors::company();
    $export = ExportFactory::new()->state(['company_id' => $company->id, 'status' => 'running', 'started_at' => now()->subMinutes(16)])->create();

    BuildExport::dispatchSync($company->id, $export->id);

    expect($export->fresh()->only(['status', 'error']))->toBe(['status' => 'failed', 'error' => 'The export stopped unexpectedly. Request it again, or export single drivers.']);
});

it('marks a timed-out or long-waiting export failed with its reason', function (string $status, string $reason) {
    $company = Actors::company();
    $export = ExportFactory::new()->state(['company_id' => $company->id, 'status' => $status])->create();

    (new BuildExport($company->id, $export->id))->failed(null);

    expect(Export::withoutTenancy()->find($export->id)->only(['status', 'error']))->toBe(['status' => 'failed', 'error' => $reason]);
})->with([
    'running' => ['running', 'The export took too long and was stopped. Export single drivers instead.'],
    'queued' => ['queued', 'The export waited too long behind other exports of the company. Request it again.'],
]);

it('keeps the file extension of a long title', function () {
    $company = Actors::company();
    [$driver, $dqFile] = Docs::dqFile($company);
    $driver->update(['first_name' => 'Jane', 'last_name' => 'Doe']);
    DocumentFactory::new()->inFolder($dqFile)->withFile(Files::stored($company->id))->create(['title' => str_repeat('Long title v1.2 ', 12)]);

    $this->actingAs($company->owner)->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id]);

    $entry = Exports::entries(Exports::latest($company))[0];
    expect($entry)->toEndWith('.pdf')
        ->and(mb_strlen(basename($entry, '.pdf')))->toBe(100);
});
