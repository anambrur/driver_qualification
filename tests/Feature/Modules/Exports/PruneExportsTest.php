<?php

use App\Models\AuditLog;
use App\Models\StoredFile;
use Database\Factories\ExportFactory;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Exports\Exports;
use Tests\Support\Actors;

/*
| exports:prune (10 §9): ready exports past expires_at lose their ZIP; queued or running exports
| older than the limit fail; nothing else changes. Export ZIPs never touch storage_bytes.
*/

beforeEach(function () {
    Storage::fake('local');
});

it('expires old exports and deletes their ZIP without touching the storage counter', function () {
    $company = Actors::company();
    $zip = Exports::zipFile($company);
    $old = ExportFactory::new()->ready($zip)->create(['expires_at' => now()->subMinute()]);
    $fresh = ExportFactory::new()->ready(Exports::zipFile($company))->create();
    $bytes = $company->fresh()->storage_bytes;

    $this->artisan('exports:prune')->assertSuccessful();

    expect($old->fresh()->only(['status', 'file_id']))->toBe(['status' => 'expired', 'file_id' => null])
        ->and(StoredFile::withoutTenancy()->find($zip->id))->toBeNull()
        ->and(Storage::disk('local')->exists($zip->path))->toBeFalse()
        ->and($fresh->fresh()->status)->toBe('ready')
        ->and($company->fresh()->storage_bytes)->toBe($bytes)
        ->and(AuditLog::query()->where('action', 'export.expired')->where('subject_id', $old->id)->exists())->toBeTrue();
});

it('fails exports that never finished', function () {
    $company = Actors::company();
    $stuck = ExportFactory::new()->state(['company_id' => $company->id, 'status' => 'running'])->create(['created_at' => now()->subHours(4), 'started_at' => now()->subHours(3)]);
    $stuckQueued = ExportFactory::new()->state(['company_id' => $company->id])->create(['created_at' => now()->subHours(3)]);
    $waiting = ExportFactory::new()->state(['company_id' => $company->id])->create(['created_at' => now()->subMinutes(10)]);
    // Queued long ago behind other exports, but building now
    $building = ExportFactory::new()->state(['company_id' => $company->id, 'status' => 'running'])->create(['created_at' => now()->subHours(3), 'started_at' => now()->subMinutes(5)]);

    $this->artisan('exports:prune')->assertSuccessful();

    expect($stuck->fresh()->only(['status', 'error']))->toBe(['status' => 'failed', 'error' => 'The export did not finish. Request it again.'])
        ->and($stuckQueued->fresh()->status)->toBe('failed')
        ->and($waiting->fresh()->status)->toBe('queued')
        ->and($building->fresh()->status)->toBe('running');
});

it('is scheduled daily', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('exports:prune');
});
