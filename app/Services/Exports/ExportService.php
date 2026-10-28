<?php

namespace App\Services\Exports;

use App\Features\Feature;
use App\Jobs\BuildExport;
use App\Models\AuditLog;
use App\Models\Export;
use App\Models\StoredFile;
use App\Models\User;
use App\Notifications\ExportFinishedNotification;
use App\Services\Entitlements\Entitlements;
use App\Services\Files\StoredFileService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ZIP exports of a driver, a trip or the whole company (req 2.5, 10 §9): requested here, built by
 * BuildExport, downloadable until expires_at, then expired by exports:prune. Requests and
 * downloads are audited (08 §10). Export files don't count toward storage (05 §5).
 */
class ExportService
{
    public function __construct(private StoredFileService $files, private TenantContext $tenant) {}

    /**
     * A ZIP holds what the user could open one by one: DQ files (drivers.view), trips (trips.view,
     * for a trip ZIP or a company ZIP with its trips) and folder documents (folders.view, when the
     * company has folders; the Starter plan has none).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function canAccess(User $user, string $type, array $parameters = []): bool
    {
        $needs = $type === 'zip_trip' ? ['trips.view'] : ['drivers.view'];

        if ($type === 'zip_company' && ! empty($parameters['trips'])) {
            $needs[] = 'trips.view';
        }
        if (in_array($type, Export::ZIP_TYPES, true) && $user->company && Entitlements::for($user->company)->allows(Feature::DocumentFolders)) {
            $needs[] = 'folders.view';
        }

        return collect($needs)->every(fn (string $permission) => $user->can($permission));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function canRequest(User $user, string $type, array $parameters = []): bool
    {
        return $user->can('exports.create') && $this->canAccess($user, $type, $parameters);
    }

    public function canDownload(User $user, Export $export): bool
    {
        return $user->can('exports.download') && $this->canAccess($user, $export->type, $export->parameters) && $export->isDownloadable();
    }

    /**
     * A queued export, or the one already queued or running for the same scope.
     *
     * @param  array<string, int>  $parameters
     */
    public function request(User $actor, string $type, array $parameters): Export
    {
        return DB::transaction(function () use ($actor, $type, $parameters) {
            // Company row first (12 §3): two requests for one scope can't both insert
            DB::table('companies')->where('id', $this->tenant->requireCompanyId())->lockForUpdate()->first();

            $pending = Export::query()->where('type', $type)->whereIn('status', Export::PENDING)->get()
                ->first(fn (Export $export) => $export->parameters == $parameters);

            if ($pending !== null) {
                return $pending;
            }

            $export = Export::query()->create([
                'requested_by_user_id' => $actor->id,
                'type' => $type,
                'parameters' => $parameters,
            ]);

            AuditLog::record($actor, $export->company_id, 'export.requested', $export, ['type' => $type] + $parameters);
            BuildExport::dispatch($export->company_id, $export->id);

            return $export;
        });
    }

    public function download(Export $export, User $actor): StreamedResponse
    {
        abort_unless($export->isDownloadable(), 404, 'Export not found');
        abort_unless($this->canAccess($actor, $export->type, $export->parameters), 403);

        $response = $this->files->response($export->file, $export->file?->original_name);
        AuditLog::record($actor, $export->company_id, 'export.downloaded', $export, ['type' => $export->type] + $export->parameters);

        return $response;
    }

    /**
     * Take a queued export for building; false when another run already took it.
     */
    public function claim(int $exportId): bool
    {
        return Export::query()->whereKey($exportId)->where('status', 'queued')
            ->update(['status' => 'running', 'started_at' => now()]) === 1;
    }

    /**
     * A redelivered build whose worker died after claiming it (an out-of-memory kill): the row
     * would otherwise stay running and block new requests for the scope.
     */
    public function failAbandoned(int $exportId, int $afterSeconds): void
    {
        $export = Export::query()->whereKey($exportId)->where('status', 'running')
            ->where('started_at', '<=', now()->subSeconds($afterSeconds))->first();

        if ($export !== null) {
            $this->markFailed($export, 'The export stopped unexpectedly. Request it again, or export single drivers.');
        }
    }

    /**
     * Must run in the transaction that stored the ZIP.
     */
    public function markReady(Export $export, StoredFile $zip): void
    {
        $export->update([
            'status' => 'ready',
            'file_id' => $zip->id,
            'error' => null,
            'finished_at' => now(),
            'expires_at' => now()->addDays((int) config('exports.keep_days')),
        ]);

        $this->notify($export);
    }

    public function markFailed(Export $export, string $error): void
    {
        $export->update(['status' => 'failed', 'error' => mb_substr($error, 0, 1000), 'finished_at' => now()]);
        AuditLog::recordSystem($export->company_id, 'export.failed', $export, ['error' => $export->error]);

        $this->notify($export);
    }

    /**
     * The ZIP is deleted once the change commits; the row stays as history.
     */
    public function expire(Export $export): void
    {
        DB::transaction(function () use ($export) {
            $fileId = $export->file_id;
            $export->update(['status' => 'expired', 'file_id' => null]);
            $this->files->purgeWhenUnreferenced($fileId);
            AuditLog::recordSystem($export->company_id, 'export.expired', $export, ['file_id' => $fileId]);
        });
    }

    private function notify(Export $export): void
    {
        $requester = $export->requestedBy()->first();
        $requester?->notify(new ExportFinishedNotification($export->id, $export->company_id));
    }
}
