<?php

use App\Models\AuditLog;
use Database\Factories\ExportFactory;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Exports\Exports;
use Tests\Support\Actors;

/*
| Downloading an export (req 2.5, 08 §10): exports.download, same company, ready and not
| expired; every download audited; the Exports list.
*/

beforeEach(function () {
    Storage::fake('local');
});

it('downloads a ready export for anyone with exports.download and audits it', function () {
    $company = Actors::company();
    $export = ExportFactory::new()->ready(Exports::zipFile($company))->create(['requested_by_user_id' => $company->owner_user_id]);
    $user = Actors::memberWithPermissions($company, ['exports.download', 'drivers.view', 'folders.view']);

    $this->actingAs($user)->get(route('exports.download', $export))
        ->assertOk()
        ->assertDownload('Company files.zip')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $audit = AuditLog::query()->where('action', 'export.downloaded')->sole();
    expect($audit->only(['company_id', 'actor_id', 'subject_id']))->toBe(['company_id' => $company->id, 'actor_id' => $user->id, 'subject_id' => $export->id]);
});

it('needs exports.download', function () {
    $company = Actors::company();
    $export = ExportFactory::new()->ready(Exports::zipFile($company))->create();
    $user = Actors::memberWithPermissions($company, ['exports.create']);

    $this->actingAs($user)->get(route('exports.download', $export))->assertForbidden();
});

it('needs drivers.view and folders.view, like the request', function () {
    $company = Actors::company();
    $export = ExportFactory::new()->ready(Exports::zipFile($company))->create();
    $user = Actors::memberWithPermissions($company, ['exports.download', 'drivers.view']);

    $this->actingAs($user)->get(route('exports.download', $export))->assertForbidden();
    expect($this->getJson(route('exports.data'))->assertOk()->json('data.0.action'))->toBe('')
        ->and(AuditLog::query()->where('action', 'export.downloaded')->exists())->toBeFalse();
});

it('404s another company\'s export', function () {
    $export = ExportFactory::new()->ready(Exports::zipFile(Actors::company()))->create();

    $this->actingAs(Actors::company()->owner)->get(route('exports.download', $export))->assertNotFound();
    expect(AuditLog::query()->where('action', 'export.downloaded')->exists())->toBeFalse();
});

it('404s an export that is not ready or has expired', function (array $state) {
    $company = Actors::company();
    $export = ExportFactory::new()->ready(Exports::zipFile($company))->create($state);

    $this->actingAs($company->owner)->get(route('exports.download', $export))->assertNotFound();
})->with([
    'queued' => [['status' => 'queued']],
    'failed' => [['status' => 'failed']],
    'past expires_at' => [['expires_at' => now()->subMinute()]],
]);

it('lists the company\'s exports with a download link only when downloadable', function () {
    $company = Actors::company();
    $ready = ExportFactory::new()->ready(Exports::zipFile($company))->create();
    $failed = ExportFactory::new()->state(['company_id' => $company->id, 'status' => 'failed', 'error' => 'Too big'])->create();
    ExportFactory::new()->ready(Exports::zipFile(Actors::company()))->create();

    $rows = $this->actingAs($company->owner)->getJson(route('exports.data'))->assertOk()->json('data');

    expect(collect($rows)->pluck('id')->sort()->values()->all())->toBe([$ready->id, $failed->id])
        ->and(collect($rows)->firstWhere('id', $ready->id)['action'])->toContain(route('exports.download', $ready))
        ->and(collect($rows)->firstWhere('id', $failed->id)['action'])->toBe('')
        ->and(collect($rows)->firstWhere('id', $failed->id)['status_badge'])->toContain('Too big');
});
