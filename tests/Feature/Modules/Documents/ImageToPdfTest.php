<?php

use App\Features\Feature;
use App\Jobs\ConvertImageToPdf;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\StoredFile;
use App\Services\Entitlements\PlanFeatureService;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Entitlements\Entitled;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| Req 2.4 (task 1.3, decided Q8): a photo uploaded into a folder becomes a one-page PDF in a
| queued job; the image is kept as the original, both count toward storage, and a failed
| conversion leaves the image in place.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->owner = Actors::companyOwner();
    $this->company = Actors::companyOf($this->owner);
    [$this->driver, $this->dqFile] = Docs::dqFile($this->company);
});

/**
 * A JPEG whose EXIF orientation is $orientation (6: the camera was turned, display it rotated 90° clockwise).
 */
function exifJpeg(int $width, int $height, int $orientation): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    // Little-endian TIFF header, one IFD entry: Orientation (0x0112), SHORT, 1 value
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, $orientation, 0).pack('V', 0);
    $app1 = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
}

/**
 * A folder document whose file holds $contents (an image unless said otherwise), in the DQ file.
 */
function imageDocument($test, string $contents, string $extension = 'jpeg'): Document
{
    return DocumentFactory::new()->inFolder($test->dqFile)->withFile(Files::stored($test->company->id, $contents, $extension))->create();
}

function convert(Document $document): void
{
    ConvertImageToPdf::dispatchSync($document->company_id, $document->id);
}

function converted(Document $document): Document
{
    return app(TenantContext::class)->run($document->company_id, fn () => Document::withTrashed()->with(['file', 'originalFile'])->findOrFail($document->id));
}

function pdfOf(Document $document): string
{
    return (string) Storage::disk('local')->get($document->file->path);
}

describe('upload', function () {
    it('queues a conversion after commit for each JPG, PNG and WebP', function () {
        Queue::fake();

        $this->actingAs($this->owner)->post(route('documents.store', $this->dqFile), ['files' => [
            UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png'), UploadedFile::fake()->image('c.webp'), Docs::pdf(),
        ]])->assertSessionHasNoErrors();

        $images = Document::withoutTenancy()->where('title', '!=', 'scan')->pluck('id')->sort()->values()->all();
        Queue::assertPushedTimes(ConvertImageToPdf::class, 3);
        Queue::assertPushed(ConvertImageToPdf::class, fn (ConvertImageToPdf $job) => $job->companyId === $this->company->id
            && in_array($job->documentId, $images, true) && $job->queue === 'default' && $job->afterCommit === true);
    });

    it('does not queue a conversion for a PDF or an office file', function () {
        Queue::fake();

        $this->actingAs($this->owner)->post(route('documents.store', $this->dqFile), ['files' => [
            Docs::pdf(), UploadedFile::fake()->create('sheet.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]])->assertSessionHasNoErrors();

        Queue::assertNotPushed(ConvertImageToPdf::class);
    });

    it('does not queue a conversion when the platform switch is off', function () {
        Queue::fake();
        config(['documents.convert_images' => false]);

        $this->actingAs($this->owner)->post(route('documents.store', $this->dqFile), ['files' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertSessionHasNoErrors();

        Queue::assertNotPushed(ConvertImageToPdf::class);
    });

    it('converts the photo when the job runs, keeping the image and counting both files', function () {
        $this->actingAs($this->owner)->post(route('documents.store', $this->dqFile), ['files' => [UploadedFile::fake()->image('licence.jpg', 800, 600)]])
            ->assertSessionHasNoErrors();

        $document = converted(Document::withoutTenancy()->sole());
        expect(pdfOf($document))->toStartWith('%PDF')
            ->and($document->file->mime_type)->toBe('application/pdf')
            ->and($document->file->original_name)->toBe('licence.pdf')
            ->and($document->file->path)->toStartWith("companies/{$this->company->id}/documents/")
            ->and($document->file->converted_from_file_id)->toBe($document->original_file_id)
            ->and($document->file->uploaded_by_user_id)->toBe($this->owner->id)
            ->and($document->originalFile->mime_type)->toBe('image/jpeg')
            ->and(Storage::disk('local')->exists($document->originalFile->path))->toBeTrue()
            ->and($this->company->fresh()->storage_bytes)->toBe($document->file->size_bytes + $document->originalFile->size_bytes);
    });
});

describe('the job', function () {
    it('applies the EXIF orientation: a landscape-encoded photo shot upright becomes a portrait page', function () {
        $document = imageDocument($this, exifJpeg(200, 100, 6));

        convert($document);

        $pdf = pdfOf(converted($document));
        expect(preg_match_all('#/Type /Page\b#', $pdf))->toBe(1)
            ->and($pdf)->toContain('/MediaBox [0.000 0.000 612.000 792.000]')
            ->and($pdf)->toContain('/Width 100')
            ->and($pdf)->toContain('/Height 200');
    });

    it('puts a wide photo on a landscape page', function () {
        $document = imageDocument($this, exifJpeg(300, 200, 1));

        convert($document);

        expect(pdfOf(converted($document)))->toContain('/MediaBox [0.000 0.000 792.000 612.000]');
    });

    it('scales an oversized photo down to the longest side cap before rendering', function () {
        $png = UploadedFile::fake()->image('wide.png', 4000, 1000);
        $document = imageDocument($this, (string) file_get_contents($png->getRealPath()), 'png');

        convert($document);

        $pdf = pdfOf(converted($document));
        expect($pdf)->toContain('/Width 3000')->and($pdf)->toContain('/Height 750');
    });

    it('runs in the document company context', function () {
        $document = imageDocument($this, exifJpeg(50, 50, 1));
        $seen = null;
        StoredFile::creating(function () use (&$seen) {
            $seen ??= app(TenantContext::class)->companyId();
        });

        convert($document);

        expect($seen)->toBe($this->company->id)
            ->and(app(TenantContext::class)->companyId())->toBeNull()
            ->and(converted($document)->file->company_id)->toBe($this->company->id);
    });

    it('does nothing for a deleted document', function () {
        $document = imageDocument($this, exifJpeg(50, 50, 1));
        app(TenantContext::class)->run($this->company->id, fn () => $document->delete());
        $bytes = $this->company->fresh()->storage_bytes;

        convert($document);

        expect(converted($document)->original_file_id)->toBeNull()
            ->and(StoredFile::withoutTenancy()->count())->toBe(1)
            ->and($this->company->fresh()->storage_bytes)->toBe($bytes);
    });

    it("does nothing for another company's document id", function () {
        $document = imageDocument($this, exifJpeg(50, 50, 1));

        ConvertImageToPdf::dispatchSync(Actors::company()->id, $document->id);

        expect(converted($document)->original_file_id)->toBeNull()
            ->and(StoredFile::withoutTenancy()->count())->toBe(1);
    });

    it('does nothing a second time', function () {
        $document = imageDocument($this, exifJpeg(50, 50, 1));

        convert($document);
        convert($document);

        expect(StoredFile::withoutTenancy()->count())->toBe(2);
    });

    it('leaves the image and logs when the image cannot be read', function () {
        Log::spy();
        $document = imageDocument($this, 'not an image');
        $bytes = $this->company->fresh()->storage_bytes;

        convert($document);

        $after = converted($document);
        expect($after->file_id)->toBe($document->file_id)
            ->and($after->original_file_id)->toBeNull()
            ->and(StoredFile::withoutTenancy()->count())->toBe(1)
            ->and(Storage::disk('local')->allFiles())->toHaveCount(1)
            ->and($this->company->fresh()->storage_bytes)->toBe($bytes);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => str_contains($message, 'Image to PDF conversion failed')
            && $context['document_id'] === $document->id)->once();
    });

    it('leaves the image when the PDF would pass the storage limit', function () {
        Log::spy();
        $document = imageDocument($this, exifJpeg(50, 50, 1));
        app(PlanFeatureService::class)->set(Actors::platformOwner(), Entitled::planOf($this->company), Feature::StorageGb, true, 1);
        Entitled::newRequest();
        DB::table('companies')->where('id', $this->company->id)->update(['storage_bytes' => 1024 ** 3 - 10]);

        convert($document);

        expect(converted($document)->original_file_id)->toBeNull()
            ->and(StoredFile::withoutTenancy()->count())->toBe(1)
            ->and(Storage::disk('local')->allFiles())->toHaveCount(1)
            ->and($this->company->fresh()->storage_bytes)->toBe(1024 ** 3 - 10);
        Log::shouldHaveReceived('warning')->once();
    });
});

describe('the document row', function () {
    it('opens the PDF inline and offers the original image, auditing both for a DQ file', function () {
        $document = imageDocument($this, exifJpeg(50, 50, 1));
        convert($document);
        $document = converted($document);

        $view = $this->actingAs($this->owner)->get(route('documents.view', $document))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($view->headers->get('Content-Disposition'))->toStartWith('inline')->toContain("{$document->title}.pdf");
        $this->get(route('documents.original', $document))
            ->assertOk()->assertDownload("{$document->title}.jpeg");
        $row = $this->getJson(route('documents.folders.documents', $this->dqFile))->assertOk()->json('data.0');
        expect($row['action'])->toContain(route('documents.original', $document))
            ->and($row['document'])->toContain(route('documents.view', $document));

        expect(AuditLog::where('action', 'document.downloaded')->where('subject_id', $document->id)->orderBy('id')->pluck('changes')->map(fn ($m) => collect($m)->except('title')->all())->all())
            ->toEqual([['inline' => true], ['original' => true]]);
    });

    it('has no original image before conversion', function () {
        $document = imageDocument($this, exifJpeg(50, 50, 1));

        $this->actingAs($this->owner)->get(route('documents.original', $document))->assertNotFound();
        expect($this->getJson(route('documents.folders.documents', $this->dqFile))->assertOk()->json('data.0.action'))
            ->not->toContain(route('documents.original', $document));
    });

    it("refuses another company's document and a user without the folder permission", function () {
        $document = imageDocument($this, exifJpeg(50, 50, 1));
        convert($document);

        foreach (['documents.view', 'documents.original'] as $route) {
            $this->actingAs(Actors::companyOwner())->get(route($route, $document))->assertNotFound();
            $this->actingAs(Actors::memberWithPermissions($this->company, ['drivers.view']))->get(route($route, $document))->assertForbidden();
        }
    });
});
