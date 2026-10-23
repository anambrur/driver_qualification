<?php

use App\Jobs\BuildExport;
use App\Models\Company;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Database\Factories\ExportFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Exports\Exports;
use Tests\Support\Actors;

/*
| 12 §5 and roadmap 13 Phase 1 acceptance: the company ZIP of a 200-driver company builds in
| under 5 minutes with flat memory and a query count that grows with chunks, not rows.
| 200 drivers × 12 files plus 50 vehicles × 2 files. Excluded from the default run; on MySQL:
| php artisan test --group=scale --filter=ExportScaleTest
*/

const EXPORT_SCALE_DRIVERS = 200;
const EXPORT_SCALE_VEHICLES = 50;

/**
 * $count stored files of 20 KB, written to the disk and inserted in one go.
 *
 * @return list<int>
 */
function exportScaleFiles(Company $company, string $batch, int $count): array
{
    $now = now();
    $contents = '%PDF-1.4 '.str_repeat('x', 20_000);
    $rows = [];

    for ($n = 1; $n <= $count; $n++) {
        $path = "companies/{$company->id}/scale/{$batch}/{$n}.pdf";
        Storage::disk('local')->put($path, $contents);
        $rows[] = ['company_id' => $company->id, 'disk' => 'local', 'path' => $path, 'original_name' => "{$n}.pdf", 'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents), 'checksum_sha256' => hash('sha256', $path), 'created_at' => $now];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('stored_files')->insert($chunk);
    }
    DB::table('companies')->where('id', $company->id)->increment('storage_bytes', strlen($contents) * $count);

    return DB::table('stored_files')->where('path', 'like', "companies/{$company->id}/scale/{$batch}/%")->orderBy('id')->pluck('id')->all();
}

function seedExportScaleCompany(Company $company): void
{
    $now = now();
    $types = collect(range(1, 5))->map(fn (int $n) => DocumentTypeFactory::new()->module('driver')->create(['name' => "Scale type {$n}"])->id)->all();

    $photos = exportScaleFiles($company, 'photos', EXPORT_SCALE_DRIVERS);
    DB::table('drivers')->insert(array_map(fn (int $photo) => ['photo_file_id' => $photo, 'ssn' => null, 'created_at' => $now, 'updated_at' => $now]
        + DriverFactory::new()->forCompany($company)->raw(), $photos));
    $drivers = DB::table('drivers')->where('company_id', $company->id)->orderBy('id')->pluck('id')->all();

    $compliance = array_chunk(exportScaleFiles($company, 'compliance', EXPORT_SCALE_DRIVERS * 5), 5);
    $application = array_chunk(exportScaleFiles($company, 'application', EXPORT_SCALE_DRIVERS * 4), 4);
    $documents = array_chunk(exportScaleFiles($company, 'documents', EXPORT_SCALE_DRIVERS * 2), 2);

    foreach ($drivers as $i => $driverId) {
        DB::table('driver_compliance_documents')->insert(array_map(fn (int $file, int $type) => [
            'company_id' => $company->id, 'driver_id' => $driverId, 'document_type_id' => $type, 'file_id' => $file,
            'file_date' => '2026-01-01', 'expiry_date' => '2027-01-01', 'created_at' => $now, 'updated_at' => $now,
        ], $compliance[$i], $types));
        DB::table('driver_documents')->insert(['driver_id' => $driverId, 'license_front_file_id' => $application[$i][0], 'license_back_file_id' => $application[$i][1],
            'medical_card_file_id' => $application[$i][2], 'forfeiture_document_file_id' => $application[$i][3], 'created_at' => $now, 'updated_at' => $now]);
        $folderId = DB::table('folders')->insertGetId(['company_id' => $company->id, 'name' => "DQ file #{$driverId}", 'system_key' => 'dq_file',
            'driver_id' => $driverId, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('documents')->insert(array_map(fn (int $file) => ['company_id' => $company->id, 'folder_id' => $folderId, 'file_id' => $file,
            'title' => "Document {$file}", 'driver_id' => $driverId, 'created_at' => $now, 'updated_at' => $now], $documents[$i]));
    }

    $vehicleType = DocumentTypeFactory::new()->module('vehicle')->create()->id;
    $vehicleFiles = array_chunk(exportScaleFiles($company, 'vehicles', EXPORT_SCALE_VEHICLES * 2), 2);
    foreach (VehicleFactory::new()->forCompany($company)->count(EXPORT_SCALE_VEHICLES)->create() as $i => $vehicle) {
        DB::table('vehicle_documents')->insert(array_map(fn (int $file) => ['company_id' => $company->id, 'vehicle_id' => $vehicle->id,
            'document_type_id' => $vehicleType, 'file_id' => $file, 'file_date' => '2026-01-01', 'expiry_date' => '2027-01-01',
            'created_at' => $now, 'updated_at' => $now], $vehicleFiles[$i]));
    }
}

it('builds the company ZIP of a 200-driver company in under 5 minutes with flat memory', function () {
    Storage::fake('local');
    $company = Actors::company();
    seedExportScaleCompany($company);
    $export = ExportFactory::new()->state(['company_id' => $company->id])->create();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });
    gc_collect_cycles();
    memory_reset_peak_usage();
    $baseline = memory_get_usage();
    $start = hrtime(true);

    BuildExport::dispatchSync($company->id, $export->id);

    $seconds = (hrtime(true) - $start) / 1e9;
    $growthMb = (memory_get_peak_usage() - $baseline) / 1024 ** 2;
    $entries = Exports::entries($export->fresh());
    fwrite(STDERR, sprintf("\nExportScale: %d drivers, %d entries, %.1f s, peak memory growth %.1f MB, %d queries, ZIP %.1f MB\n",
        EXPORT_SCALE_DRIVERS, count($entries), $seconds, $growthMb, $queries, $export->fresh()->file()->withoutGlobalScopes()->value('size_bytes') / 1024 ** 2));

    expect($export->fresh()->status)->toBe('ready')
        ->and(count($entries))->toBe(EXPORT_SCALE_DRIVERS * 12 + EXPORT_SCALE_VEHICLES * 2)
        ->and($seconds)->toBeLessThan(300.0)
        ->and($growthMb)->toBeLessThan(64.0)
        ->and($queries)->toBeLessThan(200);
})->group('scale');
