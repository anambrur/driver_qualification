<?php

namespace App\Services\Platform;

use App\Features\Feature;
use App\Models\AssetGroup;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\MaintenanceSchedule;
use App\Models\PlatformUser;
use App\Models\ServiceDocument;
use App\Models\ServiceLog;
use App\Models\StoredFile;
use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Notifications\AssetTransferredNotification;
use App\Services\Entitlements\Limits;
use App\Services\Files\StoredFileCopier;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Asset Transfer (PO-08, D11, 04 §7): the vehicle or trailer and its history are copied into the
 * target company, then the source asset is retired (soft-deleted, groups ended) and keeps its
 * history read-only. One transaction; copied objects are deleted again if it rolls back.
 */
class AssetTransferService
{
    public function __construct(private TenantContext $tenant) {}

    public function transfer(PlatformUser $actor, string $type, int $assetId, Company $source, Company $target, string $reason): AssetTransfer
    {
        if (! in_array($type, ['vehicle', 'trailer'], true)) {
            throw new InvalidArgumentException("Unknown asset type [{$type}].");
        }

        if ($source->is($target)) {
            throw ValidationException::withMessages(['target_company_id' => 'The target must be another company.']);
        }

        if ($target->status !== 'active') {
            throw ValidationException::withMessages(['target_company_id' => 'The target company is not active.']);
        }

        $copier = app(StoredFileCopier::class);
        $copying = false;

        try {
            $transfer = DB::transaction(function () use ($actor, $type, $assetId, $source, $target, $reason, $copier, &$copying) {
                // Both companies, always in id order, so two opposite transfers can't deadlock.
                Company::query()->whereKey([$source->id, $target->id])->orderBy('id')->lockForUpdate()->get();

                $history = $this->tenant->run($source->id, fn () => $this->history($type, $assetId));
                $this->assertIdentifiersFree($type, $history['asset'], $target);

                Limits::assertCanCreate($target, $type === 'vehicle' ? Feature::MaxVehicles : Feature::MaxTrailers);
                $bytes = (int) $history['files']->sum('size_bytes');
                Limits::assertCanStore($target, $bytes);

                $copying = true;
                [$copy, $counts] = $this->tenant->run($target->id, fn () => $this->copy($type, $history, $target, $copier));
                $this->tenant->run($source->id, fn () => $this->retire($history['asset']));

                $transfer = AssetTransfer::query()->create([
                    'asset_type' => $type,
                    'source_company_id' => $source->id,
                    'target_company_id' => $target->id,
                    "source_{$type}_id" => $history['asset']->id,
                    "target_{$type}_id" => $copy->id,
                    'reason' => $reason,
                    'status' => 'completed',
                    'copied_counts' => $counts,
                    'bytes_copied' => $bytes,
                    'performed_by_platform_user_id' => $actor->id,
                    'performed_at' => now(),
                ]);

                $summary = ['transfer_id' => $transfer->id, 'unit_no' => $copy->unit_no, 'reason' => $reason] + $counts;
                AuditLog::record($actor, $source->id, 'asset.transferred_out', $history['asset'], $summary + ['target_company_id' => $target->id]);
                AuditLog::record($actor, $target->id, 'asset.transferred_in', $copy, $summary + ['source_company_id' => $source->id]);

                return $transfer;
            });
        } catch (Throwable $e) {
            $copier->discardWritten();

            if ($copying) {
                $this->recordFailure($actor, $type, $assetId, $source, $target, $reason, $e);
            }

            throw $e;
        }

        $copier->forgetWritten();
        $this->notifyOwners($transfer, $source, $target);

        return $transfer;
    }

    /**
     * The live asset and everything that moves with it, read in the source company.
     *
     * @return array{asset: Vehicle|Trailer, documents: Collection<int, VehicleDocument>|Collection<int, TrailerDocument>, schedules: Collection<int, MaintenanceSchedule>, logs: Collection<int, ServiceLog>, files: Collection<int, StoredFile>}
     */
    private function history(string $type, int $assetId): array
    {
        $asset = ($type === 'vehicle' ? Vehicle::query() : Trailer::query())->lockForUpdate()->find($assetId)
            ?? throw ValidationException::withMessages(['asset_id' => 'The asset was not found in the source company.']);

        $documents = ($type === 'vehicle' ? VehicleDocument::query() : TrailerDocument::query())
            ->where("{$type}_id", $asset->id)->orderBy('id')->get();

        // Trailers have no maintenance history (service logs and schedules are per vehicle).
        $schedules = $type === 'vehicle'
            ? MaintenanceSchedule::query()->where('vehicle_id', $asset->id)->orderBy('id')->get()
            : new Collection;
        $logs = $type === 'vehicle'
            ? ServiceLog::query()->where('vehicle_id', $asset->id)->with(['maintenanceCategories', 'documents'])->orderBy('id')->get()
            : new Collection;

        $fileIds = collect([$asset->photo_file_id])
            ->merge($documents->pluck('file_id'))
            ->merge($logs->flatMap(fn (ServiceLog $log) => $log->documents->pluck('file_id')))
            ->filter()->unique()->values();

        return [
            'asset' => $asset,
            'documents' => $documents,
            'schedules' => $schedules,
            'logs' => $logs,
            'files' => StoredFile::query()->whereKey($fileIds)->get(),
        ];
    }

    /**
     * Unit no and VIN are unique per company including retired (soft-deleted) assets.
     */
    private function assertIdentifiersFree(string $type, Vehicle|Trailer $asset, Company $target): void
    {
        $taken = fn (string $column) => DB::table($type === 'vehicle' ? 'vehicles' : 'trailers')
            ->where('company_id', $target->id)
            ->where($column, $asset->{$column})
            ->exists();

        foreach (['unit_no' => 'unit number', 'vin' => 'VIN'] as $column => $label) {
            if ($asset->{$column} !== null && $taken($column)) {
                throw ValidationException::withMessages([
                    'asset_id' => "The target company already has an asset with this {$label}, including retired assets.",
                ]);
            }
        }
    }

    /**
     * Writes the copies in the target company.
     *
     * @param  array{asset: Vehicle|Trailer, documents: Collection<int, VehicleDocument>|Collection<int, TrailerDocument>, schedules: Collection<int, MaintenanceSchedule>, logs: Collection<int, ServiceLog>, files: Collection<int, StoredFile>}  $history
     * @return array{0: Vehicle|Trailer, 1: array{documents: int, service_logs: int, schedules: int, files: int}}
     */
    private function copy(string $type, array $history, Company $target, StoredFileCopier $copier): array
    {
        $files = $history['files']->keyBy('id');
        $copiedFiles = [];
        $file = function (?int $id) use ($files, $target, $copier, $type, &$copiedFiles): ?int {
            if ($id === null) {
                return null;
            }

            return $copiedFiles[$id] ??= $copier->copy($files[$id], $target, "{$type}s")->id;
        };

        $asset = $history['asset']->replicate(['company_id', 'photo_file_id']);
        $asset->forceFill(['company_id' => $target->id, 'photo_file_id' => $file($history['asset']->photo_file_id)])->save();

        foreach ($history['documents'] as $document) {
            $document->replicate(['company_id', "{$type}_id", 'file_id', 'uploaded_by_user_id'])
                ->forceFill(['company_id' => $target->id, "{$type}_id" => $asset->id, 'file_id' => $file($document->file_id)])
                ->save();
        }

        $scheduleIds = [];
        foreach ($history['schedules'] as $schedule) {
            $scheduleCopy = $schedule->replicate(['company_id', 'vehicle_id'])
                ->forceFill(['company_id' => $target->id, 'vehicle_id' => $asset->id]);
            $scheduleCopy->save();
            $scheduleIds[$schedule->id] = $scheduleCopy->id;
        }

        foreach ($history['logs'] as $log) {
            $logCopy = $log->replicate(['company_id', 'vehicle_id', 'maintenance_schedule_id'])->forceFill([
                'company_id' => $target->id,
                'vehicle_id' => $asset->id,
                // A schedule for all vehicles stays with the source company.
                'maintenance_schedule_id' => $scheduleIds[$log->maintenance_schedule_id] ?? null,
            ]);
            $logCopy->save();
            $logCopy->maintenanceCategories()->attach($log->maintenanceCategories->modelKeys());

            foreach ($log->documents as $document) {
                ServiceDocument::query()->create([
                    'service_log_id' => $logCopy->id,
                    'file_id' => $file($document->file_id),
                ]);
            }
        }

        return [$asset, [
            'documents' => $history['documents']->count(),
            'service_logs' => $history['logs']->count(),
            'schedules' => $history['schedules']->count(),
            'files' => count($copiedFiles),
        ]];
    }

    /**
     * The source keeps its records (49 CFR 396.3(c)); the asset leaves its groups (PO-08 option A).
     */
    private function retire(Vehicle|Trailer $asset): void
    {
        $column = $asset instanceof Vehicle ? 'vehicle_id' : 'trailer_id';

        AssetGroup::query()->where($column, $asset->id)->where('status', 'active')->get()
            ->each(function (AssetGroup $group) {
                $group->forceFill(['status' => 'inactive'])->save();
                $group->delete();
            });

        $asset->delete();
    }

    private function recordFailure(PlatformUser $actor, string $type, int $assetId, Company $source, Company $target, string $reason, Throwable $e): void
    {
        $failed = AssetTransfer::query()->create([
            'asset_type' => $type,
            'source_company_id' => $source->id,
            'target_company_id' => $target->id,
            "source_{$type}_id" => $assetId,
            'reason' => $reason,
            'status' => 'failed',
            'copied_counts' => ['error' => class_basename($e)],
            'performed_by_platform_user_id' => $actor->id,
            'performed_at' => now(),
        ]);

        AuditLog::record($actor, $source->id, 'asset.transfer_failed', $failed, ['target_company_id' => $target->id, 'error' => class_basename($e)]);
    }

    private function notifyOwners(AssetTransfer $transfer, Company $source, Company $target): void
    {
        foreach ([$source, $target] as $company) {
            $company->owner?->notify(new AssetTransferredNotification($transfer->id, $company->id));
        }
    }
}
