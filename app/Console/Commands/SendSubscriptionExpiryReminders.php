<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringSoonNotification;
use App\Notifications\SubscriptionExpirySmsNotification;
use App\Services\Billing\AccessStateResolver;
use App\Services\Billing\SubscriptionNotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Vonage\Client;

/**
 * Reminders before a company drops to read-only (07 §9): the trial or grant end, the end of a
 * cancelling period, or the end of the past-due grace. Sent to the Owner and billing_email.
 */
class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Send multi-channel reminders before subscription access ends';

    /** @var list<int> */
    private array $windows = [7, 3, 1, 0];

    /** Days after expiry during which a missed "expired" notice is still sent. */
    private const EXPIRED_CATCH_UP_DAYS = 2;

    public function handle(Client $vonage, SubscriptionNotificationService $notifications): int
    {
        $sent = 0;
        $graceDays = AccessStateResolver::graceDays();

        Subscription::query()
            ->with(['company.owner', 'planPrice.plan'])
            ->whereHas('company', fn (Builder $q) => $q->where('status', 'active'))
            ->where(fn (Builder $q) => $q->whereNull('ended_at')
                ->orWhere('ended_at', '>=', now()->subDays(self::EXPIRED_CATCH_UP_DAYS + 1)))
            ->where(fn (Builder $q) => $q->whereIn('status', ['trialing', 'past_due', 'canceled'])
                ->orWhere('cancel_at_period_end', true)
                ->orWhere('source', '!=', 'stripe'))
            ->chunkById(100, function ($subscriptions) use ($vonage, $notifications, $graceDays, &$sent) {
                foreach ($subscriptions as $subscription) {
                    $readOnlyAt = $subscription->readOnlyAt($graceDays);
                    $owner = $subscription->company?->owner;

                    if (! $readOnlyAt || ! $owner) {
                        continue;
                    }

                    $days = (int) now()->startOfDay()->diffInDays($readOnlyAt->copy()->startOfDay(), false);
                    // Catch-up: a missed scheduler day still sends the current window's reminder.
                    $window = $this->windowFor($days);

                    if ($window === null) {
                        continue;
                    }

                    $sent += $window === 0
                        ? $this->sendExpired($subscription, $notifications, $vonage)
                        : $this->sendExpiring($subscription, $window, $days, $notifications, $vonage);
                }
            });

        $this->info("Expiry reminders processed. Notifications recorded: {$sent}");

        return self::SUCCESS;
    }

    /**
     * The reminder window a subscription is currently in: the smallest window that is
     * still >= the days left, or 0 (expired) for a recent expiry. Logs are keyed by window.
     */
    private function windowFor(int $days): ?int
    {
        if ($days <= 0) {
            return $days >= -self::EXPIRED_CATCH_UP_DAYS ? 0 : null;
        }

        $windows = array_filter($this->windows, fn (int $window) => $window >= $days);

        return $windows === [] ? null : min($windows);
    }

    private function sendExpiring(Subscription $subscription, int $window, int $days, SubscriptionNotificationService $notifications, Client $vonage): int
    {
        $count = $this->notify($subscription, $notifications, 'expiring_soon', $window, new SubscriptionExpiringSoonNotification($subscription, $days));

        if (in_array($window, [3, 1], true)) {
            $count += $this->sendSms($subscription, $notifications, 'expiring_soon', $window, $days, $vonage);
        }

        return $count;
    }

    private function sendExpired(Subscription $subscription, SubscriptionNotificationService $notifications, Client $vonage): int
    {
        // A trial or grant has nothing in Stripe to end it: it becomes history here (07 §4.1),
        // once its end has passed. ended_at makes the company read-only at once.
        if ($subscription->source !== 'stripe' && $subscription->ended_at === null
            && ! $subscription->accessEndsAt()?->isFuture()) {
            $endedAt = $subscription->accessEndsAt() ?? now();
            $subscription->update(['status' => 'canceled', 'ends_at' => $endedAt, 'ended_at' => $endedAt]);
            AuditLog::recordSystem($subscription->company_id, 'subscription.expired', $subscription, ['ended_at' => $endedAt->toIso8601String()]);
            app(AccessStateResolver::class)->forget($subscription->company_id);
        }

        return $this->notify($subscription, $notifications, 'expired', 0, new SubscriptionExpiredNotification($subscription))
            + $this->sendSms($subscription, $notifications, 'expired', 0, 0, $vonage);
    }

    /**
     * On failure the log rows are removed so the next run retries, and the run carries on.
     */
    private function notify(Subscription $subscription, SubscriptionNotificationService $notifications, string $type, int $window, Notification $notification): int
    {
        try {
            return $notifications->notifyOnce($subscription, $type, $window, $notification) ? 1 : 0;
        } catch (\Throwable $e) {
            Log::error('Subscription expiry reminder failed', [
                'subscription_id' => $subscription->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function sendSms(Subscription $subscription, SubscriptionNotificationService $notifications, string $type, int $window, int $days, Client $vonage): int
    {
        $owner = $subscription->company->owner;
        $phone = $owner->notificationPhone();

        if (! $phone || ! $notifications->markSent($subscription, $owner->id, $type, 'sms', $window)) {
            return 0;
        }

        $text = (new SubscriptionExpirySmsNotification($subscription, $days))->toVonageSms($owner);
        if ($text && SubscriptionExpirySmsNotification::send($vonage, $phone, $text)) {
            return 1;
        }

        // Not sent (invalid number, not configured, Vonage error): allow a retry next run.
        $notifications->forget($subscription, $type, ['sms'], $window);

        return 0;
    }
}
