<?php

namespace App\Jobs;

use App\Exceptions\ExportFailed;
use App\Jobs\Middleware\TenantAware;
use App\Models\Export;
use App\Services\Exports\ExportContents;
use App\Services\Exports\ExportService;
use App\Services\Exports\ZipWriter;
use App\Services\Files\StoredFileService;
use App\Tenancy\TenantAwareJob;
use App\Tenancy\TenantContext;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Builds an export ZIP (req 2.5, 10 §9) under companies/{id}/exports/. One build at a time per
 * company: a second export waits instead of being dropped, as a unique lock would (decided
 * P1-6). Runs on the long-timeout `exports` connection, also for a read-only company (Q2).
 * Every outcome lands on the row: ready, or failed with a message for the user.
 */
class BuildExport implements ShouldQueue, TenantAwareJob
{
    use Queueable;

    public string $feature = 'zip_export';

    // A read-only company must still produce its DQ files (Q2)
    public bool $readOnly = true;

    // Below the exports connection's retry_after (960 s)
    public int $timeout = 900;

    // Waiting for another export of the company releases the job; only an exception counts
    public int $maxExceptions = 1;

    public function __construct(
        public readonly int $companyId,
        public readonly int $exportId,
    ) {
        $this->onConnection('exports');
        $this->onQueue('exports');
        $this->afterCommit();
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('exports.stuck_after_minutes'));
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            new TenantAware,
            (new WithoutOverlapping("export:{$this->companyId}"))->releaseAfter(30)->expireAfter(960),
        ];
    }

    public function handle(ExportService $exports, ExportContents $contents, StoredFileService $files): void
    {
        if (! $exports->claim($this->exportId)) {
            $exports->failAbandoned($this->exportId, $this->timeout);

            return;
        }

        $export = Export::query()->findOrFail($this->exportId);
        $zipPath = (string) tempnam(sys_get_temp_dir(), 'dq-zip-');
        $zip = null;

        try {
            $zip = new ZipWriter($zipPath, (int) config('exports.max_bytes'));
            $name = $contents->write($export, $zip);
            $zip->close();

            $files->transaction(function () use ($export, $exports, $files, $zipPath, $name) {
                // exports:prune may have failed it meanwhile; the user was told so
                if (Export::query()->lockForUpdate()->whereKey($export->id)->value('status') === 'running') {
                    $exports->markReady($export, $files->storeGenerated($zipPath, 'exports', $name, 'application/zip'));
                }
            });
        } catch (ExportFailed $e) {
            $exports->markFailed($export, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $exports->markFailed($export, 'The export could not be built. Request it again, or contact support if it keeps failing.');
        } finally {
            $zip?->cleanup();
            @unlink($zipPath);
        }
    }

    /**
     * A timeout, or too long a wait behind the company's other exports: the row must not stay
     * pending.
     */
    public function failed(?Throwable $e): void
    {
        $export = Export::withoutTenancy()->whereKey($this->exportId)->whereIn('status', Export::PENDING)->first();

        if ($export !== null) {
            app(TenantContext::class)->run($this->companyId, fn () => app(ExportService::class)->markFailed($export, $export->status === 'queued'
                ? 'The export waited too long behind other exports of the company. Request it again.'
                : 'The export took too long and was stopped. Export single drivers instead.'));
        }
    }
}
