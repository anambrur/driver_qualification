<?php

namespace App\Jobs;

use App\Models\StripeEvent;
use App\Services\Stripe\StripeEventProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies one stored Stripe event (07 §6.2). Idempotent: a processed or ignored row is skipped.
 */
class ProcessStripeEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $stripeEventId,
    ) {
        $this->onQueue('billing');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->stripeEventId;
    }

    public function handle(StripeEventProcessor $processor): void
    {
        $event = StripeEvent::query()->find($this->stripeEventId);

        if ($event) {
            $processor->process($event);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Stripe event processing failed after retries', [
            'stripe_event_id' => $this->stripeEventId,
            'error' => $e?->getMessage(),
        ]);
    }
}
