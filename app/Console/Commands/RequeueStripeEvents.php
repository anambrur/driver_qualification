<?php

namespace App\Console\Commands;

use App\Jobs\ProcessStripeEvent;
use App\Models\StripeEvent;
use Illuminate\Console\Command;

/**
 * Re-queues failed webhook events after the cause is fixed (07 §6.2).
 */
class RequeueStripeEvents extends Command
{
    protected $signature = 'stripe:requeue {event_id? : A Stripe event id (evt_...)} {--failed : Every failed event}';

    protected $description = 'Re-queue stored Stripe webhook events for processing';

    public function handle(): int
    {
        $eventId = $this->argument('event_id');

        if (! $eventId && ! $this->option('failed')) {
            $this->error('Give an event id or --failed.');

            return self::FAILURE;
        }

        $query = StripeEvent::query()->whereIn('status', ['pending', 'failed']);
        $eventId ? $query->where('event_id', $eventId) : $query->where('status', 'failed');

        // Ids first: rows leave the filtered set as they are reset.
        $ids = $query->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            StripeEvent::query()->whereKey($id)->update(['status' => 'pending', 'error' => null]);
            ProcessStripeEvent::dispatch($id);
        }

        $this->info("{$ids->count()} event(s) re-queued.");

        return self::SUCCESS;
    }
}
