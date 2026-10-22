<?php

namespace App\Services\Documents;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use App\Services\Files\StoredFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Free-form documents filed in folders (08 §8, req 2.3). The subject is the folder's binding
 * (F18), so a document in a DQ file is always about that driver. Deleting is a soft delete
 * (audited by the model); the file is kept for the retention period.
 */
class DocumentService
{
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
            $binding = $folder->binding();

            $documents = collect($uploads)->map(fn (UploadedFile $upload) => Document::query()->create([
                'folder_id' => $folder->id,
                'file_id' => $this->files->store($upload, 'documents')->id,
                'title' => ($meta['title'] ?? null) ?: mb_substr(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Document', 0, 255),
                'document_type_id' => $meta['document_type_id'] ?? null,
                'issued_on' => $meta['issued_on'] ?? null,
                'expires_on' => $meta['expires_on'] ?? null,
                'uploaded_by_user_id' => $actor->id,
            ] + ($binding === null ? [] : [$binding[0] => $binding[1]])));

            AuditLog::record($actor, $folder->company_id, 'document.uploaded', $folder, ['documents' => $documents->pluck('id')->all()]);

            return $documents;
        });
    }

    /**
     * Refiling keeps the subject, so the target must have the same binding.
     */
    public function move(Document $document, Folder $target): Document
    {
        if ($document->folder?->binding() !== $target->binding()) {
            throw ValidationException::withMessages(['folder_id' => 'A document can only move to a folder of the same driver, vehicle or trailer.']);
        }

        $document->update(['folder_id' => $target->id]);

        return $document;
    }

    public function delete(Document $document): void
    {
        $document->delete();
    }

    /**
     * Downloads of DQ-file documents are always audited (08 §10).
     */
    public function download(Document $document, User $actor): StreamedResponse
    {
        $extension = pathinfo((string) $document->file?->path, PATHINFO_EXTENSION);
        $name = str_replace(['/', '\\'], '-', $document->title).($extension === '' ? '' : ".{$extension}");
        $response = $this->files->response($document->file, $name);

        if ($document->driver_id !== null) {
            AuditLog::record($actor, $document->company_id, 'document.downloaded', $document, ['title' => $document->title]);
        }

        return $response;
    }
}
