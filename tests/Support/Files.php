<?php

namespace Tests\Support;

use App\Models\StoredFile;
use Database\Factories\StoredFileFactory;
use Illuminate\Support\Facades\Storage;

/**
 * Stored-file fixtures: an object on a (faked) disk plus its stored_files row.
 */
final class Files
{
    /**
     * @param  array<string, mixed>  $attributes  stored_files columns to override
     */
    public static function stored(int $companyId, string $contents = '%PDF-1.4 test', string $extension = 'pdf', string $disk = 'local', array $attributes = []): StoredFile
    {
        $path = sprintf('companies/%d/test/%s.%s', $companyId, bin2hex(random_bytes(8)), $extension);
        Storage::disk($disk)->put($path, $contents);

        return StoredFileFactory::new()->create($attributes + [
            'company_id' => $companyId,
            'disk' => $disk,
            'path' => $path,
            'size_bytes' => strlen($contents),
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : "image/{$extension}",
        ]);
    }

    public static function find(?int $fileId): ?StoredFile
    {
        return $fileId === null ? null : StoredFile::withoutTenancy()->find($fileId);
    }

    /**
     * The stored path behind a file id, read without a tenant context.
     */
    public static function pathOf(?int $fileId): ?string
    {
        return $fileId === null ? null : StoredFile::withoutTenancy()->whereKey($fileId)->value('path');
    }

    public static function idOf(string $path): ?int
    {
        return StoredFile::withoutTenancy()->where('path', $path)->value('id');
    }
}
