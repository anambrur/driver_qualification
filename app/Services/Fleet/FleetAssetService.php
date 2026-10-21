<?php

namespace App\Services\Fleet;

use App\Features\Feature;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Services\Entitlements\Limits;
use App\Services\Files\StoredFileService;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;

/**
 * Saves a vehicle or trailer and its photo in one transaction (req 3.5). The photo is a
 * stored_files row in the "vehicles" or "trailers" area; a replaced or removed photo is purged
 * after commit unless another row still points at it.
 */
class FleetAssetService
{
    public function __construct(private StoredFileService $files, private TenantContext $tenant) {}

    /**
     * @template T of Vehicle|Trailer
     *
     * @param  class-string<T>  $model
     * @param  array<string, mixed>  $data
     * @return T
     */
    public function create(string $model, array $data, ?UploadedFile $photo = null): Vehicle|Trailer
    {
        return $this->files->transaction(function () use ($model, $data, $photo) {
            $asset = new $model;
            Limits::assertCanCreate($this->tenant->requireCompanyId(), $asset instanceof Vehicle ? Feature::MaxVehicles : Feature::MaxTrailers);

            $asset->fill($data + ['photo_file_id' => $photo ? $this->files->store($photo, $asset->getTable())->id : null]);
            $asset->save();

            return $asset;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Vehicle|Trailer $asset, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Vehicle|Trailer
    {
        return $this->files->transaction(function () use ($asset, $data, $photo, $removePhoto) {
            if ($photo || $removePhoto) {
                $this->files->purgeWhenUnreferenced($asset->photo_file_id);
                $data['photo_file_id'] = $photo ? $this->files->store($photo, $asset->getTable())->id : null;
            }

            $asset->update($data);

            return $asset;
        });
    }
}
