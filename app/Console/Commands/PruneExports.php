<?php

namespace App\Console\Commands;

use App\Models\Export;
use App\Services\Exports\ExportService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Expires ready exports past expires_at and deletes their ZIPs (10 §9), and fails exports that
 * stayed queued or running too long (a dropped job or a killed worker; decided P1-6).
 */
class PruneExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Expire old export ZIPs and fail exports that never finished';

    public function handle(ExportService $exports, TenantContext $tenant): int
    {
        $expired = 0;
        $failed = 0;

        Export::withoutTenancy()->where('status', 'ready')->where('expires_at', '<=', now())
            ->chunkById(100, function ($chunk) use ($exports, $tenant, &$expired) {
                foreach ($chunk as $export) {
                    $tenant->run($export->company_id, fn () => $exports->expire($export));
                    $expired++;
                }
            });

        $cutoff = now()->subMinutes((int) config('exports.stuck_after_minutes'));
        Export::withoutTenancy()
            ->where(fn ($q) => $q->where(fn ($queued) => $queued->where('status', 'queued')->where('created_at', '<=', $cutoff))
                ->orWhere(fn ($running) => $running->where('status', 'running')->where('started_at', '<=', $cutoff)))
            ->chunkById(100, function ($chunk) use ($exports, $tenant, &$failed) {
                foreach ($chunk as $export) {
                    $tenant->run($export->company_id, fn () => $exports->markFailed($export, 'The export did not finish. Request it again.'));
                    $failed++;
                }
            });

        $this->info("{$expired} export(s) expired, {$failed} unfinished export(s) failed.");

        return self::SUCCESS;
    }
}
