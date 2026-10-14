<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Stripe\StripeClientFactory;
use App\Services\Stripe\SubscriptionSync;
use Illuminate\Console\Command;

class SyncPaymentsFromStripe extends Command
{
    protected $signature = 'payments:sync-from-stripe
                            {--subscription= : Local subscription ID or Stripe subscription ID}
                            {--company= : Limit to a company ID}';

    protected $description = 'Backfill company payments from paid Stripe invoices (repair)';

    public function handle(StripeClientFactory $stripe, SubscriptionSync $sync): int
    {
        $query = Subscription::query()
            ->with('company')
            ->whereNotNull('stripe_subscription_id')
            ->where('source', 'stripe');

        if ($id = $this->option('subscription')) {
            $query->where(fn ($q) => $q->where('id', $id)->orWhere('stripe_subscription_id', $id));
        }

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        $total = 0;

        $query->chunkById(50, function ($subscriptions) use ($stripe, $sync, &$total) {
            foreach ($subscriptions as $subscription) {
                try {
                    $count = 0;

                    foreach ($stripe->make()->invoices->all([
                        'subscription' => $subscription->stripe_subscription_id,
                        'status' => 'paid',
                        'limit' => 100,
                    ])->autoPagingIterator() as $invoice) {
                        $sync->recordInvoice($subscription->company, $invoice, 'paid');
                        $count++;
                    }

                    $total += $count;
                    $this->line("  #{$subscription->id} {$subscription->stripe_subscription_id}: {$count} payment(s)");
                } catch (\Throwable $e) {
                    $this->error("  #{$subscription->id} failed: {$e->getMessage()}");
                }
            }
        });

        $this->info("Done. Upserted/confirmed {$total} payment row(s).");

        return self::SUCCESS;
    }
}
