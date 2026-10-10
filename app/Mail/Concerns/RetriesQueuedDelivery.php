<?php

namespace App\Mail\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Retry policy for queued driver mail. SendQueuedMailable copies $tries and calls
 * backoff()/failed() on the mailable, so a transient SMTP error is retried and a
 * permanently failed send is logged instead of only landing in failed_jobs.
 */
trait RetriesQueuedDelivery
{
    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Queued mail delivery failed', [
            'mailable' => static::class,
            'driver_id' => $this->driver->id,
            'company_id' => $this->driver->company_id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
