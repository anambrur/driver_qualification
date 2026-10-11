<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Free trials have no Stripe subscription, so nothing ever moved an ended trial off
 * stripe_status = 'trialing' (PO-22). Access was already refused by the trial's end date; this
 * only makes the status say so too, for the admin lists and reports.
 */
class CloseExpiredTrials extends Command
{
    protected $signature = 'subscriptions:close-expired-trials {--dry-run : Only count the trials that would be closed}';

    protected $description = 'Mark free trials whose end date has passed as canceled';

    public function handle(): int
    {
        $expired = Subscription::query()
            ->whereNull('stripe_subscription_id')
            ->where('stripe_status', 'trialing')
            ->where(fn ($q) => $q->where('source', 'trial')->orWhere('billing_cycle', 'trial'))
            // Same fallback order as Subscription::accessEndsAt()
            ->whereRaw('COALESCE(ends_at, trial_ends_at, current_period_end) < ?', [now()]);

        if ($this->option('dry-run')) {
            $this->info($expired->count().' expired trial(s) would be marked canceled.');

            return self::SUCCESS;
        }

        $closed = $expired->update(['stripe_status' => 'canceled']);

        $this->info("{$closed} expired trial(s) marked canceled.");

        return self::SUCCESS;
    }
}
