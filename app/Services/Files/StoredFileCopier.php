<?php

namespace App\Services\Files;

use App\Models\Company;
use App\Models\StoredFile;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Copies a stored object into another company: a new object under the target's prefix
 * (companies/{id}/{area}/{yyyy}/{mm}/…), a new stored_files row there, and the target's
 * storage_bytes raised in the same transaction. The caller checks the storage limit first and
 * removes the written paths if its transaction rolls back.
 */
class StoredFileCopier
{
    /** @var list<array{disk: string, path: string}> */
    private array $written = [];

    public function __construct(private TenantContext $tenant) {}

    public function copy(StoredFile $source, Company $target, string $area): StoredFile
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('StoredFileCopier::copy must run inside the transaction that records the file.');
        }

        $extension = pathinfo($source->path, PATHINFO_EXTENSION);
        $path = sprintf('companies/%d/%s/%s/%s%s', $target->id, $area, now()->format('Y/m'), Str::lower(Str::random(40)), $extension === '' ? '' : ".{$extension}");

        $stream = Storage::disk($source->disk)->readStream($source->path)
            ?? throw new RuntimeException("Stored file {$source->id} is missing from disk {$source->disk}.");

        try {
            Storage::disk($source->disk)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->written[] = ['disk' => $source->disk, 'path' => $path];

        $copy = $this->tenant->run($target->id, fn () => StoredFile::query()->create([
            'disk' => $source->disk,
            'path' => $path,
            'original_name' => $source->original_name,
            'mime_type' => $source->mime_type,
            'size_bytes' => $source->size_bytes,
            'checksum_sha256' => $source->checksum_sha256,
            'uploaded_by_user_id' => null,
        ]));

        DB::table('companies')->where('id', $target->id)->increment('storage_bytes', $source->size_bytes);

        return $copy;
    }

    /**
     * Deletes every object written since the last reset (after a rollback).
     */
    public function discardWritten(): void
    {
        foreach ($this->written as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        $this->written = [];
    }

    public function forgetWritten(): void
    {
        $this->written = [];
    }
}
