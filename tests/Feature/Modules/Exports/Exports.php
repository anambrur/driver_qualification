<?php

namespace Tests\Feature\Modules\Exports;

use App\Models\Company;
use App\Models\Export;
use App\Models\StoredFile;
use App\Services\Files\StoredFileService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Export fixtures (req 2.5).
 */
final class Exports
{
    public static function latest(Company $company): ?Export
    {
        return Export::withoutTenancy()->where('company_id', $company->id)->latest('id')->first();
    }

    /**
     * The ZIP's entry names, sorted.
     *
     * @return list<string>
     */
    public static function entries(Export $export): array
    {
        $file = StoredFile::withoutTenancy()->findOrFail($export->file_id);
        $zip = new ZipArchive;
        expect($zip->open(Storage::disk($file->disk)->path($file->path)))->toBeTrue();

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();
        sort($names);

        return $names;
    }

    /**
     * A ZIP stored in the exports area the way BuildExport stores it.
     */
    public static function zipFile(Company $company): StoredFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'dq-test-zip-');
        file_put_contents($path, 'PK test');
        $files = app(StoredFileService::class);

        try {
            return app(TenantContext::class)->run($company->id, fn () => $files->transaction(
                fn () => $files->storeGenerated($path, 'exports', 'Company files.zip', 'application/zip')
            ));
        } finally {
            @unlink($path);
        }
    }
}
