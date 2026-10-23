<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\PlatformUser;
use App\Notifications\CompanyPurgedNotification;
use App\Services\Company\CompanyPurgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The purge started from the platform company page (08 §15). Platform work, not a tenant job.
 * Runs on the long-timeout `exports` connection; the service re-checks the retention rules under
 * a lock, so a second click or a changed company is refused there. Owners are told either way.
 */
class RunCompanyPurge implements ShouldQueue
{
    use Queueable;

    // Below the exports connection's retry_after (960 s); a failed purge is not retried blindly
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public readonly int $companyId,
        public readonly string $companyName,
        public readonly int $actorId,
        public readonly string $reason,
    ) {
        $this->onConnection('exports');
        $this->onQueue('exports');
        $this->afterCommit();
    }

    public function handle(CompanyPurgeService $purge): void
    {
        $company = Company::query()->find($this->companyId);

        // Already purged by an earlier click
        if ($company === null) {
            return;
        }

        $deleted = $purge->purge($company, PlatformUser::query()->find($this->actorId), $this->reason);
        $this->notifyOwners($deleted, null);
    }

    public function failed(?Throwable $e): void
    {
        $this->notifyOwners([], $e?->getMessage() ?? 'The purge did not finish.');
    }

    /**
     * @param  array<string, int>  $deleted
     */
    private function notifyOwners(array $deleted, ?string $error): void
    {
        $actor = PlatformUser::query()->find($this->actorId);
        $owners = PlatformUser::query()->activeOwners()->get();

        Notification::send($owners, new CompanyPurgedNotification(
            $this->companyId, $this->companyName, $actor->name ?? 'Platform staff', $this->reason, $deleted, $error,
        ));
    }
}
