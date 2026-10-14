<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\BillingRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification implements ShouldQueue
{
    use BillingRecipient, Queueable;

    public function __construct(public Subscription $subscription) {}

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->planName();

        return (new MailMessage)
            ->subject('Your Subscription Has Expired')
            ->greeting($this->greetingFor($notifiable))
            ->line("Your {$planName} subscription has expired.")
            ->line('Your company can still view and export its records, but cannot add or change them until you renew.')
            ->action('Renew Your Subscription', route('pricing.plans'))
            ->line('If you have any questions, please contact our support team.')
            ->salutation('Best regards, '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_expired',
            'plan' => $this->subscription->planName(),
            'message' => 'Your subscription has expired.',
            'action' => route('pricing.plans'),
        ];
    }
}
