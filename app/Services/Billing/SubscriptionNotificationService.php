<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionNotificationLog;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionActivatedNotification;
use Throwable;

class SubscriptionNotificationService
{
    /**
     * Send activation confirmation once per subscription (mail + database).
     */
    public function sendActivated(Subscription $subscription): bool
    {
        $subscription->loadMissing(['user', 'plan']);
        $user = $subscription->user;

        if (! $user) {
            return false;
        }

        $mailOk = $this->markSent($subscription, $user->id, 'activated', 'mail', 0);
        $dbOk = $this->markSent($subscription, $user->id, 'activated', 'database', 0);

        if ($mailOk || $dbOk) {
            $user->notify(new SubscriptionActivatedNotification($subscription));

            return true;
        }

        return false;
    }

    /**
     * Send payment-failed notice at most once per invoice id.
     */
    public function sendPaymentFailed(Subscription $subscription, string $invoiceId, ?string $hostedInvoiceUrl = null): bool
    {
        $subscription->loadMissing(['user', 'plan']);
        $user = $subscription->user;

        if (! $user || $invoiceId === '') {
            return false;
        }

        $type = 'payment_failed:'.$invoiceId;
        $mailOk = $this->markSent($subscription, $user->id, $type, 'mail', 0);
        $dbOk = $this->markSent($subscription, $user->id, $type, 'database', 0);

        if ($mailOk || $dbOk) {
            $user->notify(new PaymentFailedNotification($subscription, $hostedInvoiceUrl));

            return true;
        }

        return false;
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
        } catch (Throwable) {
            return false;
        }
    }
}
