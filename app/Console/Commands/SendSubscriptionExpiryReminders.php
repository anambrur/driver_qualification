<?php

namespace App\Console\Commands;

use App\Events\SubscriptionExpired;
use App\Events\SubscriptionExpiringSoon;
use App\Models\Subscription;
use App\Models\SubscriptionNotificationLog;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringSoonNotification;
use App\Notifications\SubscriptionExpirySmsNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Vonage\Client;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Send multi-channel reminders before subscription access ends';

    /** @var list<int> */
    private array $windows = [7, 3, 1, 0];

    /** Days after expiry during which a missed "expired" notice is still sent. */
    private const EXPIRED_CATCH_UP_DAYS = 2;

    public function handle(Client $vonage): int
    {
        $sent = 0;

        Subscription::query()
            ->with(['user.company', 'plan'])
            ->where(function ($q) {
                $q->where('source', 'trial')
                    ->orWhere('billing_cycle', 'trial')
                    ->orWhere('source', 'admin')
                    ->orWhere('cancel_at_period_end', true)
                    ->orWhere('stripe_status', 'canceled')
                    ->orWhere('stripe_status', 'past_due');
            })
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($vonage, &$sent) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->shouldReceiveExpiryReminders()) {
                        continue;
                    }

                    $days = $subscription->daysUntilAccessEnds();
                    if ($days === null) {
                        continue;
                    }

                    // Catch-up: a missed scheduler day still sends the current window's reminder.
                    $window = $this->windowFor($days);
                    $user = $subscription->user;
                    if ($window === null || ! $user) {
                        continue;
                    }

                    $sent += $window === 0
                        ? $this->sendExpired($subscription, $user, $vonage)
                        : $this->sendExpiring($subscription, $user, $window, $days, $vonage);
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

    private function sendExpiring(Subscription $subscription, $user, int $window, int $days, Client $vonage): int
    {
        $count = 0;

        $mailOk = $this->markSent($subscription, $user->id, 'expiring_soon', 'mail', $window);
        $dbOk = $this->markSent($subscription, $user->id, 'expiring_soon', 'database', $window);

        if ($mailOk || $dbOk) {
            // Single notification covers mail + database channels.
            if ($this->notify($subscription, $user, new SubscriptionExpiringSoonNotification($subscription, $days), 'expiring_soon', $window)) {
                $count++;
                event(new SubscriptionExpiringSoon($subscription, $days));
            }
        }

        if (in_array($window, [3, 1], true)) {
            $count += $this->sendSms($subscription, $user, 'expiring_soon', $window, $days, $vonage);
        }

        return $count;
    }

    private function sendExpired(Subscription $subscription, $user, Client $vonage): int
    {
        $count = 0;

        if (in_array($subscription->source, ['trial', 'admin'], true)
            || $subscription->billing_cycle === 'trial') {
            if ($subscription->stripe_status !== 'canceled') {
                $subscription->update([
                    'stripe_status' => 'canceled',
                    'ends_at' => $subscription->accessEndsAt() ?? now(),
                ]);
            }
        }

        $mailOk = $this->markSent($subscription, $user->id, 'expired', 'mail', 0);
        $dbOk = $this->markSent($subscription, $user->id, 'expired', 'database', 0);

        if ($mailOk || $dbOk) {
            if ($this->notify($subscription, $user, new SubscriptionExpiredNotification($subscription), 'expired', 0)) {
                $count++;
                event(new SubscriptionExpired($subscription));
            }
        }

        $count += $this->sendSms($subscription, $user, 'expired', 0, 0, $vonage);

        return $count;
    }

    /**
     * Send the mail + database notification. On failure the log rows are removed so the
     * next run retries, and the run carries on with the other subscriptions.
     */
    private function notify(Subscription $subscription, $user, Notification $notification, string $type, int $window): bool
    {
        try {
            $user->notify($notification);

            return true;
        } catch (\Throwable $e) {
            $this->forgetSent($subscription, $type, ['mail', 'database'], $window);
            Log::error('Subscription expiry reminder failed', [
                'subscription_id' => $subscription->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendSms(Subscription $subscription, $user, string $type, int $window, int $days, Client $vonage): int
    {
        $phone = $user->notificationPhone();
        if (! $phone || ! $this->markSent($subscription, $user->id, $type, 'sms', $window)) {
            return 0;
        }

        $text = (new SubscriptionExpirySmsNotification($subscription, $days))->toVonageSms($user);
        if ($text && SubscriptionExpirySmsNotification::send($vonage, $phone, $text)) {
            return 1;
        }

        // Not sent (invalid number, not configured, Vonage error): allow a retry next run.
        $this->forgetSent($subscription, $type, ['sms'], $window);

        return 0;
    }

    /**
     * @param  list<string>  $channels
     */
    private function forgetSent(Subscription $subscription, string $type, array $channels, int $daysBefore): void
    {
        SubscriptionNotificationLog::query()
            ->where('subscription_id', $subscription->id)
            ->where('type', $type)
            ->whereIn('channel', $channels)
            ->where('days_before', $daysBefore)
            ->delete();
    }

    private function alreadySent(int $subscriptionId, string $type, string $channel, int $daysBefore): bool
    {
        return SubscriptionNotificationLog::query()
            ->where('subscription_id', $subscriptionId)
            ->where('type', $type)
            ->where('channel', $channel)
            ->where('days_before', $daysBefore)
            ->exists();
    }

    private function markSent(Subscription $subscription, int $userId, string $type, string $channel, int $daysBefore): bool
    {
        if ($this->alreadySent($subscription->id, $type, $channel, $daysBefore)) {
            return false;
        }

        try {
            SubscriptionNotificationLog::create([
                'subscription_id' => $subscription->id,
                'user_id' => $userId,
                'type' => $type,
                'channel' => $channel,
                'days_before' => $daysBefore,
                'sent_at' => now(),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
