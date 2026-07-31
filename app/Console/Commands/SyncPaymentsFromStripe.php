<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Stripe\WebhookProcessor;
use Illuminate\Console\Command;

class SyncPaymentsFromStripe extends Command
{
    protected $signature = 'payments:sync-from-stripe
                            {--subscription= : Local subscription ID or Stripe subscription ID}
                            {--user= : Limit to a local user ID}';

    protected $description = 'Backfill local payments from paid Stripe invoices (dual-write repair)';

    public function handle(WebhookProcessor $processor): int
    {
        $query = Subscription::query()
            ->whereNotNull('stripe_subscription_id')
            ->where('source', 'stripe');

        if ($id = $this->option('subscription')) {
            $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('stripe_subscription_id', $id);
            });
        }

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }

        $total = 0;
        $subs = $query->get();

        if ($subs->isEmpty()) {
            $this->warn('No Stripe-linked subscriptions found.');

            return self::SUCCESS;
        }

        $this->info("Syncing paid invoices for {$subs->count()} subscription(s)...");

        foreach ($subs as $subscription) {
            try {
                $count = $processor->syncPaidInvoicesForSubscription($subscription);
                $total += $count;
                $this->line("  #{$subscription->id} {$subscription->stripe_subscription_id}: {$count} payment(s)");
            } catch (\Throwable $e) {
                $this->error("  #{$subscription->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Done. Upserted/confirmed {$total} payment row(s).");

        return self::SUCCESS;
    }
}
