<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionNotificationLog;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\SubscriptionExpiredNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * Billing notices to the company's Owner and billing_email (07 §9), each sent once per
 * subscription, type, channel and window (the log is the dedupe key).
 */
class SubscriptionNotificationService
{
    public function sendActivated(Subscription $subscription): bool
    {
        return $this->notifyOnce($subscription, 'activated', 0, new SubscriptionActivatedNotification($subscription));
    }

    /**
     * At most once per invoice id.
     */
    public function sendPaymentFailed(Subscription $subscription, string $invoiceId, ?string $hostedInvoiceUrl = null): bool
    {
        if ($invoiceId === '') {
            return false;
        }

        return $this->notifyOnce($subscription, 'payment_failed:'.$invoiceId, 0, new PaymentFailedNotification($subscription, $hostedInvoiceUrl));
    }

    /**
     * Stripe deleted the subscription. Shares the "expired" key with the reminder command, so
     * the company gets one notice, not two.
     */
    public function sendCanceled(Subscription $subscription): bool
    {
        return $this->notifyOnce($subscription, 'expired', 0, new SubscriptionExpiredNotification($subscription));
    }

    /**
     * Mail and database to the Owner, mail to billing_email. On failure the log rows are removed
     * and the error rethrown, so the next run or webhook retry resends.
     */
    public function notifyOnce(Subscription $subscription, string $type, int $daysBefore, Notification $notification): bool
    {
        $subscription->loadMissing(['company.owner', 'planPrice.plan']);
        $owner = $subscription->company?->owner;

        if (! $owner) {
            return false;
        }

        $mailOk = $this->markSent($subscription, $owner->id, $type, 'mail', $daysBefore);
        $dbOk = $this->markSent($subscription, $owner->id, $type, 'database', $daysBefore);

        if (! $mailOk && ! $dbOk) {
            return false;
        }

        try {
            $owner->notify($notification);

            if ($billingEmail = $this->billingEmail($subscription->company, $owner)) {
                Notifier::route('mail', $billingEmail)->notify($notification);
            }
        } catch (Throwable $e) {
            $this->forget($subscription, $type, ['mail', 'database'], $daysBefore);

            throw $e;
        }

        return true;
    }

    public function markSent(Subscription $subscription, ?int $recipientId, string $type, string $channel, int $daysBefore): bool
    {
        $exists = SubscriptionNotificationLog::query()
            ->where('subscription_id', $subscription->id)
            ->where('type', $type)
            ->where('channel', $channel)
            ->where('days_before', $daysBefore)
            ->exists();

        if ($exists) {
            return false;
        }

        try {
            SubscriptionNotificationLog::query()->create([
                'company_id' => $subscription->company_id,
                'subscription_id' => $subscription->id,
                'recipient_user_id' => $recipientId,
                'type' => $type,
                'channel' => $channel,
                'days_before' => $daysBefore,
                'sent_at' => now(),
            ]);

            return true;
        } catch (Throwable) {
            // Lost the race to the unique index: another run sent it.
            return false;
        }
    }

    /**
     * @param  list<string>  $channels
     */
    public function forget(Subscription $subscription, string $type, array $channels, int $daysBefore): void
    {
        SubscriptionNotificationLog::query()
            ->where('subscription_id', $subscription->id)
            ->where('type', $type)
            ->whereIn('channel', $channels)
            ->where('days_before', $daysBefore)
            ->delete();
    }

    private function billingEmail(Company $company, User $owner): ?string
    {
        $email = mb_strtolower(trim((string) $company->billing_email));

        return $email !== '' && $email !== $owner->email ? $email : null;
    }
}
