<?php

namespace App\Services\Compliance;

use App\Models\DriverComplianceDocument;
use App\Models\TrailerDocument;
use App\Models\VehicleDocument;
use App\Services\Files\StoredFileService;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Files one upload as a compliance document of each owner (12 §2 P1 #4): the file is stored
 * once, each owner's current document of the type is repointed in one update, and the missing
 * ones are bulk-inserted. Replaced files are purged after commit if nothing else uses them.
 */
class ComplianceDocumentUploader
{
    private const CHUNK = 500;

    public function __construct(private StoredFileService $files, private TenantContext $tenant) {}

    /**
     * @param  class-string<DriverComplianceDocument|VehicleDocument|TrailerDocument>  $model
     * @param  list<int>  $ownerIds  ids from a tenant-scoped query, or one authorized owner
     * @param  array<string, mixed>  $fields
     * @return array{created: int, updated: int}
     */
    public function upload(string $model, string $ownerColumn, array $ownerIds, int $documentTypeId, array $fields, UploadedFile $upload, string $area): array
    {
        return $this->files->transaction(function () use ($model, $ownerColumn, $ownerIds, $documentTypeId, $fields, $upload, $area) {
            $file = $this->files->store($upload, $area);
            $fields += ['uploaded_by_user_id' => Auth::guard('web')->id()];

            // The oldest document of each type is the one that counts (FleetComplianceService)
            $current = $model::query()
                ->whereIn($ownerColumn, $ownerIds)
                ->where('document_type_id', $documentTypeId)
                ->selectRaw("MIN(id) AS id, {$ownerColumn}")
                ->groupBy($ownerColumn)
                ->pluck('id', $ownerColumn);

            if ($current->isNotEmpty()) {
                $replaced = $model::query()->whereKey($current->values()->all())->pluck('file_id')->all();
                $model::query()->whereKey($current->values()->all())->update($fields + ['file_id' => $file->id]);
                $this->files->purgeWhenUnreferenced(...$replaced);
            }

            $missing = array_values(array_diff($ownerIds, $current->keys()->all()));
            $companyId = $this->tenant->requireCompanyId();
            $now = now();

            foreach (array_chunk($missing, self::CHUNK) as $chunk) {
                $model::query()->insert(array_map(fn (int $ownerId) => $fields + [
                    'company_id' => $companyId,
                    $ownerColumn => $ownerId,
                    'document_type_id' => $documentTypeId,
                    'file_id' => $file->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            return ['created' => count($missing), 'updated' => $current->count()];
        });
    }
}
