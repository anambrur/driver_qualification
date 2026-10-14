<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A free trial has no Stripe subscription, so nothing else ends it (PO-22). Its access already
 * stopped at trial_ends_at; this makes it history (07 §4.1, §5) so the company is read-only.
 */
class CloseExpiredTrials extends Command
{
    protected $signature = 'subscriptions:close-expired-trials {--dry-run : Only count the trials that would be closed}';

    protected $description = 'Close free trials whose end date has passed';

    public function handle(): int
    {
        $expired = Subscription::query()
            ->where('source', 'trial')
            ->whereNull('ended_at')
            ->where('trial_ends_at', '<=', now());

        if ($this->option('dry-run')) {
            $this->info($expired->count().' expired trial(s) would be closed.');

            return self::SUCCESS;
        }

        $closed = $expired->update([
            'status' => 'canceled',
            'ends_at' => DB::raw('trial_ends_at'),
            'ended_at' => DB::raw('trial_ends_at'),
            'updated_at' => now(),
        ]);

        $this->info("{$closed} expired trial(s) closed.");

        return self::SUCCESS;
    }
}
