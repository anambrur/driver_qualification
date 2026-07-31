<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your plan';
        $dayLabel = $this->daysRemaining === 1 ? '1 day' : "{$this->daysRemaining} days";

        return (new MailMessage)
            ->subject("Your subscription expires in {$dayLabel}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$planName} subscription will end in {$dayLabel}.")
            ->line('Renew now to keep uninterrupted access.')
            ->action('Choose a Plan', route('pricing.plans'))
            ->salutation('Best regards, '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_expiring_soon',
            'plan' => $this->subscription->plan?->name,
            'days_remaining' => $this->daysRemaining,
            'message' => "Your subscription expires in {$this->daysRemaining} day(s).",
            'action' => route('pricing.plans'),
        ];
    }
}
