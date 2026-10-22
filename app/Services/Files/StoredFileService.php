<?php

namespace App\Services\Files;

use App\Models\StoredFile;
use App\Services\Entitlements\Limits;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Uploads become stored_files rows (08 §stored_files): a private object under
 * companies/{id}/{area}/{yyyy}/{mm}/…, the row, and companies.storage_bytes raised in the same
 * transaction after the storage limit check (05 §5). Many rows may share one file ("upload to
 * all"); a file is purged only once no RESTRICT FK points at it.
 */
class StoredFileService
{
    public const DISK = 'local';

    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];

    /** @var list<array{disk: string, path: string}> */
    private array $written = [];

    public function __construct(private TenantContext $tenant) {}

    /**
     * Run $callback in a transaction; objects written by store() are deleted if it fails.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        try {
            $result = DB::transaction($callback);
        } catch (Throwable $e) {
            $this->discardWritten();

            throw $e;
        }

        $this->written = [];

        return $result;
    }

    public function store(UploadedFile $upload, string $area): StoredFile
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('StoredFileService::store must run inside the transaction that records the file.');
        }

        $companyId = $this->tenant->requireCompanyId();
        $bytes = (int) $upload->getSize();
        Limits::assertCanStore($companyId, $bytes);

        // hashName(): random name, extension taken from the content, never the client's
        $path = $upload->storeAs(sprintf('companies/%d/%s/%s', $companyId, $area, now()->format('Y/m')), $upload->hashName(), self::DISK)
            ?: throw new RuntimeException('Could not store the uploaded file.');
        $this->written[] = ['disk' => self::DISK, 'path' => $path];

        $file = StoredFile::query()->create([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => mb_substr($upload->getClientOriginalName(), 0, 255),
            'mime_type' => (string) $upload->getMimeType(),
            'size_bytes' => $bytes,
            'checksum_sha256' => hash_file('sha256', (string) $upload->getRealPath()),
            'uploaded_by_user_id' => Auth::guard('web')->id(),
        ]);

        DB::table('companies')->where('id', $companyId)->increment('storage_bytes', $bytes);

        return $file;
    }

    /**
     * Content the app made (a PDF from an image, a ZIP) at $localPath, stored like store(): the
     * same path convention, storage limit and counter. A conversion links its source file.
     */
    public function storeGenerated(string $localPath, string $area, string $name, string $mimeType, ?StoredFile $convertedFrom = null): StoredFile
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('StoredFileService::storeGenerated must run inside the transaction that records the file.');
        }

        $companyId = $this->tenant->requireCompanyId();
        $bytes = (int) filesize($localPath);
        Limits::assertCanStore($companyId, $bytes);

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $path = Storage::disk(self::DISK)->putFileAs(
            sprintf('companies/%d/%s/%s', $companyId, $area, now()->format('Y/m')),
            new File($localPath),
            Str::random(40).($extension === '' ? '' : '.'.strtolower($extension)),
        ) ?: throw new RuntimeException('Could not store the generated file.');
        $this->written[] = ['disk' => self::DISK, 'path' => $path];

        $file = StoredFile::query()->create([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => mb_substr($name, 0, 255),
            'mime_type' => $mimeType,
            'size_bytes' => $bytes,
            'checksum_sha256' => hash_file('sha256', $localPath),
            'converted_from_file_id' => $convertedFrom?->id,
            'uploaded_by_user_id' => $convertedFrom !== null ? $convertedFrom->uploaded_by_user_id : Auth::guard('web')->id(),
        ]);

        DB::table('companies')->where('id', $companyId)->increment('storage_bytes', $bytes);

        return $file;
    }

    /**
     * Purge each file once the current transaction commits, unless something still points at it.
     * A failed purge is reported, not thrown: the save has committed and must keep its new file.
     */
    public function purgeWhenUnreferenced(?int ...$fileIds): void
    {
        $ids = array_values(array_unique(array_filter($fileIds)));

        if ($ids !== []) {
            DB::afterCommit(function () use ($ids) {
                foreach ($ids as $id) {
                    try {
                        $this->purgeIfUnreferenced($id);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
        }
    }

    public function purgeIfUnreferenced(int $fileId): void
    {
        $file = StoredFile::query()->find($fileId);

        if ($file === null) {
            return;
        }

        try {
            DB::transaction(function () use ($file) {
                DB::table('companies')->where('id', $file->company_id)->lockForUpdate()->first();
                $file->delete();
                DB::table('companies')->where('id', $file->company_id)->decrement('storage_bytes', $file->size_bytes);
            });
        } catch (QueryException $e) {
            // A RESTRICT FK refused the delete: another row still uses the file
            if ($e->getCode() === '23000') {
                return;
            }

            throw $e;
        }

        Storage::disk($file->disk)->delete($file->path);
    }

    /**
     * The file inline, or as a download under $downloadName; inline under that name when $inline.
     * The caller authorizes access.
     */
    public function response(?StoredFile $file, ?string $downloadName = null, bool $inline = false): StreamedResponse
    {
        abort_unless($file !== null && Storage::disk($file->disk)->exists($file->path), 404, 'File not found');

        return $downloadName === null || $inline
            ? Storage::disk($file->disk)->response($file->path, $downloadName, self::HEADERS)
            : Storage::disk($file->disk)->download($file->path, $downloadName, self::HEADERS);
    }

    private function discardWritten(): void
    {
        foreach ($this->written as $object) {
            Storage::disk($object['disk'])->delete($object['path']);
        }

        $this->written = [];
    }
}
