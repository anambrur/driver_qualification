<?php

use App\Features\Feature;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\StoredFile;
use App\Services\Entitlements\PlanFeatureService;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Entitlements\Entitled;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| Req 2.3 (task 1.2): uploads into a folder go through StoredFileService (private disk, storage
| limit, storage_bytes); the subject is the folder's binding (F18); DQ downloads and deletes are
| audited (08 §10).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->owner = Actors::companyOwner();
    $this->company = Actors::companyOf($this->owner);
    [$this->driver, $this->dqFile] = Docs::dqFile($this->company);
});

function upload($test, $folder, array $data)
{
    return $test->actingAs($test->owner)->post(route('documents.store', $folder), $data + ['_dialog' => 'document-upload']);
}

it('stores each file on the private disk, raises storage_bytes and files it with the driver as subject', function () {
    $medical = Docs::childOf($this->dqFile, ['name' => 'Medical']);
    $before = $this->company->fresh()->storage_bytes;

    upload($this, $medical, ['files' => [Docs::pdf('card.pdf', 100), Docs::pdf('letter.pdf', 50)], 'issued_on' => '2026-01-10', 'expires_on' => '2027-01-10'])
        ->assertRedirect(route('admin.driver.show', [$this->driver->id, 'folder' => $medical->id]).'#documents')
        ->assertSessionHasNoErrors();

    $documents = app(TenantContext::class)->run($this->company->id, fn () => Document::query()->with('file')->orderBy('id')->get());
    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('title')->all())->toBe(['card', 'letter'])
        ->and($documents->pluck('folder_id')->unique()->all())->toBe([$medical->id])
        ->and($documents->pluck('driver_id')->unique()->all())->toBe([$this->driver->id])
        ->and($documents->first()->expires_on->toDateString())->toBe('2027-01-10')
        ->and($documents->every(fn (Document $d) => str_starts_with($d->file->path, "companies/{$this->company->id}/documents/")
            && Storage::disk('local')->exists($d->file->path)))->toBeTrue()
        ->and($this->company->fresh()->storage_bytes)->toBe($before + (int) $documents->sum(fn ($d) => $d->file->size_bytes))
        ->and(AuditLog::where('action', 'document.uploaded')->where('subject_id', $medical->id)->exists())->toBeTrue();
});

it('files a company-level upload without a subject and takes the title for one file', function () {
    $folder = Docs::companyFolder($this->company);

    upload($this, $folder, ['files' => [Docs::pdf()], 'title' => 'Insurance policy 2026'])->assertSessionHasNoErrors();

    $document = Document::withoutTenancy()->sole();
    expect($document->title)->toBe('Insurance policy 2026')
        ->and(collect(['driver_id', 'vehicle_id', 'trailer_id'])->map(fn ($c) => $document->{$c})->filter()->all())->toBe([]);
});

it('refuses the upload when the storage limit would be passed (422), writing nothing', function () {
    app(PlanFeatureService::class)->set(Actors::platformOwner(), Entitled::planOf($this->company), Feature::StorageGb, true, 1);
    Entitled::newRequest();
    DB::table('companies')->where('id', $this->company->id)->update(['storage_bytes' => 1024 ** 3 - 50_000]);

    $this->actingAs($this->owner)->postJson(route('documents.store', $this->dqFile), ['files' => [Docs::pdf('big.pdf', 100)]])
        ->assertStatus(422)->assertJsonPath('limit.feature', 'storage_gb');

    expect(Document::withoutTenancy()->count())->toBe(0)
        ->and(StoredFile::withoutTenancy()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($this->company->fresh()->storage_bytes)->toBe(1024 ** 3 - 50_000);
});

it('validates the files and the metadata', function (Closure $data, string $field) {
    $vehicleType = DocumentTypeFactory::new()->module('vehicle')->create();

    upload($this, $this->dqFile, $data($vehicleType))->assertSessionHasErrors($field);

    expect(Document::withoutTenancy()->count())->toBe(0);
})->with([
    'no file' => [fn () => [], 'files'],
    'HEIC photo' => [fn () => ['files' => [UploadedFile::fake()->create('IMG_0001.HEIC', 200, 'image/heic')]], 'files.0'],
    'executable' => [fn () => ['files' => [UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')]], 'files.0'],
    'over 20 MB' => [fn () => ['files' => [Docs::pdf('huge.pdf', 20 * 1024 + 1)]], 'files.0'],
    'more than 10 files' => [fn () => ['files' => array_map(fn ($n) => Docs::pdf("f{$n}.pdf", 1), range(1, 11))], 'files'],
    'a title with two files' => [fn () => ['files' => [Docs::pdf('a.pdf'), Docs::pdf('b.pdf')], 'title' => 'One title'], 'title'],
    'a vehicle type in a DQ file' => [fn ($type) => ['files' => [Docs::pdf()], 'document_type_id' => $type->id], 'document_type_id'],
    'expiry before issue' => [fn () => ['files' => [Docs::pdf()], 'issued_on' => '2026-05-01', 'expires_on' => '2026-04-01'], 'expires_on'],
]);

it('says why a HEIC photo is refused', function () {
    upload($this, $this->dqFile, ['files' => [UploadedFile::fake()->create('IMG_0001.heic', 200, 'image/heic')]])
        ->assertSessionHasErrors(['files.0' => 'HEIC photos aren\'t supported yet. Export as JPG or PDF and upload again.']);
});

it('accepts a document type of the folder module that the company has not switched off', function () {
    $type = DocumentTypeFactory::new()->module('driver')->create();

    upload($this, $this->dqFile, ['files' => [Docs::pdf()], 'document_type_id' => $type->id])->assertSessionHasNoErrors();

    expect(Document::withoutTenancy()->sole()->document_type_id)->toBe($type->id);
});

it('moves a document between folders of the same driver only', function () {
    $medical = Docs::childOf($this->dqFile, ['name' => 'Medical']);
    $document = DocumentFactory::new()->inFolder($this->dqFile)->create();
    $vehicleFolder = Docs::systemFolder(VehicleFactory::new()->forCompany($this->company)->create());

    $this->actingAs($this->owner)->patch(route('documents.move', $document), ['folder_id' => $vehicleFolder->id])->assertSessionHasErrors('folder_id');
    expect($document->fresh()->folder_id)->toBe($this->dqFile->id);

    $this->patch(route('documents.move', $document), ['folder_id' => $medical->id])->assertSessionHasNoErrors();
    expect($document->fresh())->folder_id->toBe($medical->id)->driver_id->toBe($this->driver->id);
});

it('downloads a DQ-file document and audits it', function () {
    $file = Files::stored($this->company->id, '%PDF-1.4 medical');
    $document = DocumentFactory::new()->inFolder($this->dqFile)->withFile($file)->create(['title' => 'Medical card']);

    $this->actingAs($this->owner)->get(route('documents.download', $document))
        ->assertOk()->assertDownload('Medical card.pdf')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect(AuditLog::where('action', 'document.downloaded')->where('subject_id', $document->id)->where('actor_id', $this->owner->id)->exists())->toBeTrue();
});

it('does not audit downloads of company-level documents', function () {
    $file = Files::stored($this->company->id);
    $document = DocumentFactory::new()->inFolder(Docs::companyFolder($this->company))->withFile($file)->create();

    $this->actingAs($this->owner)->get(route('documents.download', $document))->assertOk();

    expect(AuditLog::where('action', 'document.downloaded')->exists())->toBeFalse();
});

it('soft-deletes a document, keeps its file and audits the delete', function () {
    $file = Files::stored($this->company->id);
    $document = DocumentFactory::new()->inFolder($this->dqFile)->withFile($file)->create();

    $this->actingAs($this->owner)->delete(route('documents.destroy', $document))->assertRedirect();

    expect($document->fresh()->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($file->path))->toBeTrue()
        ->and(AuditLog::where('action', 'document.deleted')->where('subject_id', $document->id)->exists())->toBeTrue();
});

it('lists only the live documents of the folder', function () {
    $medical = Docs::childOf($this->dqFile, ['name' => 'Medical']);
    DocumentFactory::new()->inFolder($medical)->create(['title' => 'Kept']);
    DocumentFactory::new()->inFolder($medical)->create(['title' => 'Gone'])->delete();
    DocumentFactory::new()->inFolder($this->dqFile)->create(['title' => 'Elsewhere']);

    $this->actingAs($this->owner)->getJson(route('documents.folders.documents', $medical).'?draw=1&start=0&length=10')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('recordsTotal', 1)
        ->assertSee('Kept');
});

it('offers Move only when the binding has another folder', function () {
    DocumentFactory::new()->inFolder($this->dqFile)->create();
    $list = fn () => $this->actingAs($this->owner)->getJson(route('documents.folders.documents', $this->dqFile).'?draw=1&start=0&length=10');

    $list()->assertOk()->assertDontSee('data-move-document', false);
    Docs::childOf($this->dqFile);
    $list()->assertOk()->assertSee('data-move-document', false);
});
