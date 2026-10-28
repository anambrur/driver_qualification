<?php

namespace App\Services\Exports;

use App\Exceptions\ExportFailed;
use App\Models\Company;
use App\Models\Document;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Models\DriverDocument;
use App\Models\Export;
use App\Models\Folder;
use App\Models\ServiceLog;
use App\Models\StoredFile;
use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * What an export ZIP holds (Q8: everything attached to the entity) and where:
 * Drivers/<Last, First> (#id)/<document type | Application | folder path>/<file>, the same for
 * Vehicles/ and Trailers/ by unit number, Trips/<number> (#id)/<folder path>/<file> (the trip
 * folder holds the event, fuel and inspection files too), and Company folders/. Rows are read in
 * chunks with their files eager loaded, so queries grow with chunks, not rows. Soft-deleted rows
 * are left out; the company ZIP also leaves out draft applications and keeps terminated drivers
 * and cancelled trips, and has Trips/ only when requested with them. A stored file is added
 * once per entity.
 */
class ExportContents
{
    private const CHUNK = 50;

    /**
     * Adds the export's files to $zip and returns the ZIP's download name.
     */
    public function write(Export $export, ZipWriter $zip): string
    {
        $date = now()->toDateString();

        if ($export->type === 'zip_driver') {
            $driver = Driver::query()->find($export->driverId()) ?? throw new ExportFailed('The driver no longer exists.');
            $this->drivers(Driver::query()->whereKey($driver->id), $zip);

            return ZipWriter::sanitise('DQ file - '.self::driverFolder($driver)." - {$date}").'.zip';
        }

        if ($export->type === 'zip_trip') {
            $trip = Trip::query()->find($export->tripId()) ?? throw new ExportFailed('The trip no longer exists.');
            $this->trips(Trip::query()->whereKey($trip->id), $zip);

            return ZipWriter::sanitise("Trip files - {$trip->trip_number} - {$date}").'.zip';
        }

        $this->drivers(Driver::query()->listed(), $zip);
        $this->vehicles($zip);
        $this->trailers($zip);
        if ($export->withTrips()) {
            $this->trips(Trip::query(), $zip);
        }
        $this->companyFolders($zip);
        $company = Company::query()->findOrFail($export->company_id);

        return ZipWriter::sanitise("Company files - {$company->company_name} - {$date}").'.zip';
    }

    /**
     * @param  Builder<Driver>  $drivers
     */
    private function drivers(Builder $drivers, ZipWriter $zip): void
    {
        $relations = array_map(fn (string $field) => 'driver_documents.'.Str::camel($field).'File', DriverDocument::FILE_FIELDS);

        $drivers->with(['photoFile', ...$relations, 'documents' => fn ($q) => $q->with(['file', 'documentType' => fn ($t) => $t->withTrashed()])->orderBy('id')])
            ->chunkById(self::CHUNK, function (Collection $chunk) use ($zip) {
                $folders = $this->entityFolders('driver_id', $chunk->modelKeys());

                foreach ($chunk as $driver) {
                    $base = ['Drivers', self::driverFolder($driver)];
                    $seen = [];
                    $this->compliance($zip, $base, $driver->documents, $seen);

                    foreach (DriverDocument::FILE_FIELDS as $field) {
                        $file = $driver->driver_documents?->{Str::camel($field).'File'};
                        $this->addOnce($zip, $seen, [...$base, 'Application'], Str::ucfirst(str_replace('_', ' ', $field)), $file);
                    }

                    $this->addOnce($zip, $seen, $base, 'Photo', $driver->photoFile);
                    $this->folderDocuments($zip, $seen, $base, $folders, $driver->id);
                }
            });
    }

    private function vehicles(ZipWriter $zip): void
    {
        $this->assets(Vehicle::query(), 'Vehicles', 'vehicle_id', $zip, function (Collection $chunk) {
            return ServiceLog::query()->whereIn('vehicle_id', $chunk->modelKeys())->with('documents.file')->orderBy('id')->get()->groupBy('vehicle_id');
        });
    }

    private function trailers(ZipWriter $zip): void
    {
        $this->assets(Trailer::query(), 'Trailers', 'trailer_id', $zip);
    }

    /**
     * @param  Builder<Vehicle>|Builder<Trailer>  $assets
     * @param  (callable(Collection<int, Vehicle>): \Illuminate\Support\Collection<int|string, Collection<int, ServiceLog>>)|null  $serviceLogs
     */
    private function assets(Builder $assets, string $root, string $column, ZipWriter $zip, ?callable $serviceLogs = null): void
    {
        $assets->with(['photoFile', 'documents' => fn ($q) => $q->with(['file', 'documentType' => fn ($t) => $t->withTrashed()])->orderBy('id')])
            ->chunkById(self::CHUNK, function (Collection $chunk) use ($root, $column, $zip, $serviceLogs) {
                $folders = $this->entityFolders($column, $chunk->modelKeys());
                $logs = $serviceLogs === null ? collect() : $serviceLogs($chunk);

                foreach ($chunk as $asset) {
                    $base = [$root, "{$asset->unit_no} (#{$asset->id})"];
                    $seen = [];
                    $this->compliance($zip, $base, $asset->documents, $seen);
                    $this->addOnce($zip, $seen, $base, 'Photo', $asset->photoFile);

                    foreach ($logs->get($asset->id, []) as $log) {
                        foreach ($log->documents as $document) {
                            $name = pathinfo((string) $document->file?->original_name, PATHINFO_FILENAME) ?: 'Service document';
                            $this->addOnce($zip, $seen, [...$base, 'Service logs', $log->service_date?->toDateString()." (#{$log->id})"], $name, $document->file);
                        }
                    }

                    $this->folderDocuments($zip, $seen, $base, $folders, $asset->id);
                }
            });
    }

    /**
     * @param  Builder<Trip>  $trips
     */
    private function trips(Builder $trips, ZipWriter $zip): void
    {
        $trips->select(['id', 'company_id', 'trip_number'])->chunkById(self::CHUNK, function (Collection $chunk) use ($zip) {
            $folders = $this->entityFolders('trip_id', $chunk->modelKeys());

            foreach ($chunk as $trip) {
                $seen = [];
                $this->folderDocuments($zip, $seen, ['Trips', "{$trip->trip_number} (#{$trip->id})"], $folders, $trip->id);
            }
        });
    }

    private function companyFolders(ZipWriter $zip): void
    {
        $folders = Folder::query()->boundTo(null)->get();
        $documents = $this->documentsIn($folders);
        $seen = [];

        foreach ($folders as $folder) {
            $this->documents($zip, $seen, ['Company folders', ...$this->path($folder, $folders->keyBy('id'))], $documents->get($folder->id, []));
        }
    }

    /**
     * The folders bound to the chunk's entities, by entity id, with their documents.
     *
     * @param  array<int, mixed>  $entityIds
     * @return array{byEntity: \Illuminate\Support\Collection<int|string, Collection<int, Folder>>, byId: Collection<int, Folder>, documents: \Illuminate\Support\Collection<int|string, Collection<int, Document>>}
     */
    private function entityFolders(string $column, array $entityIds): array
    {
        $folders = Folder::query()->whereIn($column, $entityIds)->orderBy('id')->get();

        return [
            'byEntity' => $folders->groupBy($column),
            'byId' => $folders->keyBy('id'),
            'documents' => $this->documentsIn($folders),
        ];
    }

    /**
     * @param  Collection<int, Folder>  $folders
     * @return \Illuminate\Support\Collection<int|string, Collection<int, Document>>
     */
    private function documentsIn(Collection $folders): \Illuminate\Support\Collection
    {
        return Document::query()->whereIn('folder_id', $folders->modelKeys())->with(['file', 'originalFile'])->orderBy('id')->get()->groupBy('folder_id');
    }

    /**
     * @param  list<string>  $base
     * @param  array<int, true>  $seen
     * @param  array{byEntity: \Illuminate\Support\Collection<int|string, Collection<int, Folder>>, byId: Collection<int, Folder>, documents: \Illuminate\Support\Collection<int|string, Collection<int, Document>>}  $folders
     */
    private function folderDocuments(ZipWriter $zip, array &$seen, array $base, array $folders, int $entityId): void
    {
        foreach ($folders['byEntity']->get($entityId, []) as $folder) {
            $this->documents($zip, $seen, [...$base, ...$this->path($folder, $folders['byId'])], $folders['documents']->get($folder->id, []));
        }
    }

    /**
     * A document's file (the PDF once converted) and its original image.
     *
     * @param  list<string>  $folders
     * @param  array<int, true>  $seen
     * @param  iterable<Document>  $documents
     */
    private function documents(ZipWriter $zip, array &$seen, array $folders, iterable $documents): void
    {
        foreach ($documents as $document) {
            $this->addOnce($zip, $seen, $folders, $document->title, $document->file);
            $this->addOnce($zip, $seen, $folders, "{$document->title} (original)", $document->originalFile);
        }
    }

    /**
     * Typed compliance documents, one folder per document type.
     *
     * @param  list<string>  $base
     * @param  array<int, true>  $seen
     * @param  iterable<DriverComplianceDocument|VehicleDocument|TrailerDocument>  $documents
     */
    private function compliance(ZipWriter $zip, array $base, iterable $documents, array &$seen): void
    {
        foreach ($documents as $document) {
            $type = $document->documentType->name ?? 'Document';
            $date = $document->expiry_date ?? $document->file_date;
            $this->addOnce($zip, $seen, [...$base, $type], $type.($date === null ? '' : ' '.$date->toDateString()), $document->file);
        }
    }

    /**
     * The folder's labels from its root down.
     *
     * @param  Collection<int, Folder>  $byId
     * @return list<string>
     */
    private function path(Folder $folder, Collection $byId): array
    {
        $path = [];

        for ($step = $folder; $step !== null; $step = $step->parent_id === null ? null : $byId->get($step->parent_id)) {
            array_unshift($path, $step->label());
        }

        return $path;
    }

    /**
     * @param  list<string>  $folders
     * @param  array<int, true>  $seen
     */
    private function addOnce(ZipWriter $zip, array &$seen, array $folders, string $name, ?StoredFile $file): void
    {
        if ($file === null || isset($seen[$file->id])) {
            return;
        }

        $seen[$file->id] = true;
        $zip->add($folders, $name, pathinfo($file->path, PATHINFO_EXTENSION), $file);
    }

    private static function driverFolder(Driver $driver): string
    {
        $name = trim("{$driver->last_name}, {$driver->first_name}", ' ,');

        return ($name === '' ? 'Driver' : $name)." (#{$driver->id})";
    }
}
