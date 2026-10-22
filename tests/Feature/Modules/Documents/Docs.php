<?php

namespace Tests\Feature\Modules\Documents;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Folder;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Services\Documents\FolderService;
use App\Tenancy\TenantContext;
use Database\Factories\DriverFactory;
use Database\Factories\FolderFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Folders and documents fixtures (req 2.1–2.3).
 */
final class Docs
{
    /**
     * The entity's standard folder, through the service the create paths use.
     */
    public static function systemFolder(Driver|Vehicle|Trailer $entity): Folder
    {
        return app(TenantContext::class)->run($entity->company_id, fn () => DB::transaction(
            fn () => app(FolderService::class)->ensureSystemFolder($entity)
        ));
    }

    /**
     * A hired driver of $company and its DQ file.
     *
     * @return array{0: Driver, 1: Folder}
     */
    public static function dqFile(Company $company): array
    {
        $driver = DriverFactory::new()->forCompany($company)->create();

        return [$driver, self::systemFolder($driver)];
    }

    /**
     * A custom folder below $parent, with its binding.
     */
    public static function childOf(Folder $parent, array $attributes = []): Folder
    {
        return FolderFactory::new()->childOf($parent)->create($attributes);
    }

    /**
     * A company-level folder.
     */
    public static function companyFolder(Company $company, array $attributes = []): Folder
    {
        return FolderFactory::new()->forCompany($company)->create($attributes);
    }

    public static function pdf(string $name = 'scan.pdf', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }
}
