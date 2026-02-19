<?php
// ══════════════════════════════════════════════════════════════════
// app/Notifications/SubscriptionExpiredNotification.php
// ══════════════════════════════════════════════════════════════════

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Subscription Has Expired')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$this->subscription->plan->name} subscription has expired.")
            ->line('You no longer have access to the system.')
            ->action('Renew Your Subscription', route('subscription.plans'))
            ->line('If you have any questions, please contact our support team.')
            ->salutation('Best regards, ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'subscription_expired',
            'plan'    => $this->subscription->plan->name,
            'message' => 'Your subscription has expired.',
            'action'  => route('subscription.plans'),
        ];
    }
}
