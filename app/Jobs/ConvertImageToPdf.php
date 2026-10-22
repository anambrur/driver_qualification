<?php

namespace App\Jobs;

use App\Exceptions\ImageNotConvertible;
use App\Exceptions\LimitReached;
use App\Jobs\Middleware\TenantAware;
use App\Models\Document;
use App\Services\Documents\DocumentService;
use App\Services\Documents\ImageToPdfConverter;
use App\Tenancy\TenantAwareJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Turns a folder document's photo into a PDF (req 2.4, 12 §3). Idempotent: a deleted, replaced
 * or already converted document is left alone. Until it succeeds the document keeps its image;
 * an unreadable image or a full storage quota fails at once, since a retry can't help.
 */
class ConvertImageToPdf implements ShouldBeUnique, ShouldQueue, TenantAwareJob
{
    use Queueable;

    public string $feature = 'document_folders';

    public int $tries = 3;

    // Below the queue connections' retry_after (90 s), so a slow run is not picked up twice
    public int $timeout = 60;

    // The lock is taken at dispatch; it must expire if the upload transaction rolls back
    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $companyId,
        public readonly int $documentId,
    ) {
        $this->onQueue('default');
        $this->afterCommit();
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new TenantAware];
    }

    public function handle(ImageToPdfConverter $converter, DocumentService $documents): void
    {
        $document = Document::query()->with('file')->find($this->documentId);
        $image = $document?->file;

        if ($image === null || $document->original_file_id !== null || ! in_array($image->mime_type, DocumentService::CONVERTIBLE, true)) {
            return;
        }

        $pdfPath = (string) tempnam(sys_get_temp_dir(), 'dq-pdf-');

        try {
            $bytes = Storage::disk($image->disk)->get($image->path)
                ?? throw new RuntimeException("Stored file {$image->id} could not be read.");
            file_put_contents($pdfPath, $converter->render($bytes));
            $documents->attachConvertedPdf($document, $image, $pdfPath);
        } catch (ImageNotConvertible|LimitReached $e) {
            $this->fail($e);
        } finally {
            @unlink($pdfPath);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('Image to PDF conversion failed; the document keeps its image', [
            'company_id' => $this->companyId,
            'document_id' => $this->documentId,
            'error' => $e?->getMessage(),
        ]);
    }
}
