<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Vonage\Client;
use Vonage\SMS\Message\SMS;

class SubscriptionExpirySmsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining
    ) {}

    public function via(object $notifiable): array
    {
        return ['vonage_sms'];
    }

    /**
     * Custom channel handled by sendViaVonage when using Notification::route
     * or by the dedicated sender in the command. Kept for interface completeness.
     */
    public function toVonageSms(object $notifiable): ?string
    {
        $phone = method_exists($notifiable, 'notificationPhone')
            ? $notifiable->notificationPhone()
            : null;

        if (! $phone) {
            return null;
        }

        $planName = $this->subscription->plan?->name ?? 'subscription';

        if ($this->daysRemaining <= 0) {
            return "Your {$planName} on ".config('app.name').' has expired. Renew: '.route('pricing.plans');
        }

        $dayLabel = $this->daysRemaining === 1 ? '1 day' : "{$this->daysRemaining} days";

        return "Your {$planName} on ".config('app.name')." ends in {$dayLabel}. Renew: ".route('pricing.plans');
    }

    public static function send(Client $vonage, string $to, string $text): bool
    {
        $from = config('services.vonage.sms_from');
        if (! $from || ! config('services.vonage.api_key') || ! config('services.vonage.api_secret')) {
            Log::info('Vonage SMS skipped: not configured');

            return false;
        }

        try {
            $vonage->sms()->send(new SMS($to, $from, $text));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Vonage SMS failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
