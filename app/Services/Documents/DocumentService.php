<?php

namespace App\Services\Documents;

use App\Jobs\ConvertImageToPdf;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Folder;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\StoredFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Free-form documents filed in folders (08 §8, req 2.3). The subject is the folder's binding
 * (F18), so a document in a DQ file is always about that driver. Deleting is a soft delete
 * (audited by the model); the file is kept for the retention period.
 */
class DocumentService
{
    public const CONVERTIBLE = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private StoredFileService $files) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  array{title?: string|null, document_type_id?: int|null, issued_on?: string|null, expires_on?: string|null}  $meta
     * @return Collection<int, Document>
     */
    public function upload(Folder $folder, array $uploads, array $meta, User $actor): Collection
    {
        return $this->files->transaction(function () use ($folder, $uploads, $meta, $actor) {
            $folder = Folder::query()->lockForUpdate()->findOrFail($folder->id);

            $documents = collect($uploads)->map(function (UploadedFile $upload) use ($folder, $meta, $actor) {
                $file = $this->files->store($upload, 'documents');

                return Document::query()->create([
                    'folder_id' => $folder->id,
                    'file_id' => $file->id,
                    'title' => ($meta['title'] ?? null) ?: mb_substr(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Document', 0, 255),
                    'document_type_id' => $meta['document_type_id'] ?? null,
                    'issued_on' => $meta['issued_on'] ?? null,
                    'expires_on' => $meta['expires_on'] ?? null,
                    'uploaded_by_user_id' => $actor->id,
                ] + $folder->bindingAttributes())->setRelation('file', $file);
            });

            AuditLog::record($actor, $folder->company_id, 'document.uploaded', $folder, ['documents' => $documents->pluck('id')->all()]);

            // Photos become PDFs once the upload commits (req 2.4); a platform switch (05 §2)
            if (config('documents.convert_images')) {
                $documents->filter(fn (Document $document) => in_array($document->file->mime_type, self::CONVERTIBLE, true))
                    ->each(fn (Document $document) => ConvertImageToPdf::dispatch($document->company_id, $document->id));
            }

            return $documents;
        });
    }

    /**
     * Point the document at the PDF made from its image, keeping the image as the original
     * (req 2.4). Skipped when the document was deleted, replaced or converted meanwhile.
     */
    public function attachConvertedPdf(Document $document, StoredFile $image, string $pdfPath): void
    {
        $this->files->transaction(function () use ($document, $image, $pdfPath) {
            $document = Document::query()->lockForUpdate()->find($document->id);

            if ($document === null || $document->file_id !== $image->id || $document->original_file_id !== null) {
                return;
            }

            $name = (pathinfo($image->original_name, PATHINFO_FILENAME) ?: 'Document').'.pdf';
            $pdf = $this->files->storeGenerated($pdfPath, 'documents', $name, 'application/pdf', $image);
            $document->update(['file_id' => $pdf->id, 'original_file_id' => $image->id]);
        });
    }

    /**
     * Refiling keeps the subject, so the target must have the same binding. The target is locked
     * like FolderService::delete locks it, so a document can't land in a folder being deleted.
     */
    public function move(Document $document, Folder $target): Document
    {
        return DB::transaction(function () use ($document, $target) {
            $target = Folder::query()->lockForUpdate()->findOrFail($target->id);
            $current = $document->folder;

            if ($current === null || $current->binding() !== $target->binding()) {
                throw ValidationException::withMessages(['folder_id' => 'A document can only move to a folder of the same driver, vehicle or trailer.']);
            }

            $document->update(['folder_id' => $target->id]);

            return $document;
        });
    }

    public function delete(Document $document): void
    {
        $document->delete();
    }

    /**
     * The document's file (the PDF once converted) as a download, or inline when $inline.
     * Downloads of DQ-file documents are always audited (08 §10), inline views included.
     */
    public function download(Document $document, User $actor, bool $inline = false): StreamedResponse
    {
        return $this->serve($document, $document->file, $actor, $this->downloadName($document, $document->file), ['inline' => $inline]);
    }

    /**
     * The image a converted document was made from (req 2.4).
     */
    public function downloadOriginal(Document $document, User $actor): StreamedResponse
    {
        abort_if($document->original_file_id === null, 404, 'File not found');

        return $this->serve($document, $document->originalFile, $actor, $this->downloadName($document, $document->originalFile), ['original' => true]);
    }

    /**
     * @param  array<string, bool>  $meta
     */
    private function serve(Document $document, ?StoredFile $file, User $actor, string $name, array $meta): StreamedResponse
    {
        $response = $this->files->response($file, $name, $meta['inline'] ?? false);

        if ($document->driver_id !== null) {
            AuditLog::record($actor, $document->company_id, 'document.downloaded', $document, ['title' => $document->title] + array_filter($meta));
        }

        return $response;
    }

    private function downloadName(Document $document, ?StoredFile $file): string
    {
        $extension = pathinfo((string) $file?->path, PATHINFO_EXTENSION);

        return str_replace(['/', '\\'], '-', $document->title).($extension === '' ? '' : ".{$extension}");
    }
}
