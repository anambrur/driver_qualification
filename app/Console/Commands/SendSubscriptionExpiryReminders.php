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
use Vonage\Client;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Send multi-channel reminders before subscription access ends';

    /** @var list<int> */
    private array $windows = [7, 3, 1, 0];

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

                    foreach ($this->windows as $window) {
                        if ($days !== $window) {
                            continue;
                        }

                        $user = $subscription->user;
                        if (! $user) {
                            continue;
                        }

                        $sent += $window === 0
                            ? $this->sendExpired($subscription, $user, $vonage)
                            : $this->sendExpiring($subscription, $user, $window, $vonage);
                    }
                }
            });

        $this->info("Expiry reminders processed. Notifications recorded: {$sent}");

        return self::SUCCESS;
    }

    private function sendExpiring(Subscription $subscription, $user, int $days, Client $vonage): int
    {
        $count = 0;

        $mailOk = $this->markSent($subscription, $user->id, 'expiring_soon', 'mail', $days);
        $dbOk = $this->markSent($subscription, $user->id, 'expiring_soon', 'database', $days);

        if ($mailOk || $dbOk) {
            // Single notification covers mail + database channels.
            $user->notify(new SubscriptionExpiringSoonNotification($subscription, $days));
            $count++;
        }

        if (in_array($days, [3, 1], true)) {
            $phone = $user->notificationPhone();
            if ($phone && $this->markSent($subscription, $user->id, 'expiring_soon', 'sms', $days)) {
                $text = (new SubscriptionExpirySmsNotification($subscription, $days))->toVonageSms($user);
                if ($text && SubscriptionExpirySmsNotification::send($vonage, $phone, $text)) {
                    $count++;
                }
            }
        }

        event(new SubscriptionExpiringSoon($subscription, $days));

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
            $user->notify(new SubscriptionExpiredNotification($subscription));
            $count++;
        }

        $phone = $user->notificationPhone();
        if ($phone && $this->markSent($subscription, $user->id, 'expired', 'sms', 0)) {
            $text = (new SubscriptionExpirySmsNotification($subscription, 0))->toVonageSms($user);
            if ($text && SubscriptionExpirySmsNotification::send($vonage, $phone, $text)) {
                $count++;
            }
        }

        event(new SubscriptionExpired($subscription));

        return $count;
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
