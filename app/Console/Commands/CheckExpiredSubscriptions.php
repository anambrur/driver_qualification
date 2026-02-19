<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class CheckExpiredSubscriptions extends Command
{
    protected $signature   = 'subscriptions:check-expired {--dry-run : Show what would be expired without making changes}';
    protected $description = 'Mark expired subscriptions and send expiry warning notifications';

    public function __construct(private SubscriptionService $subscriptionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking subscriptions...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        if (!$this->option('dry-run')) {
            // Mark expired
            $expiredCount = $this->subscriptionService->processExpiredSubscriptions();
            $this->info("✓ Expired {$expiredCount} subscription(s).");

            // Send warnings
            $this->subscriptionService->sendExpiryWarnings();
            $this->info('✓ Expiry warning notifications dispatched.');
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
