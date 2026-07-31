<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your plan';
        $sourceLabel = match ($this->subscription->source) {
            'trial' => 'free trial',
            'admin' => 'complimentary subscription',
            default => 'subscription',
        };
        $endsAt = $this->subscription->accessEndsAt()?->format('F j, Y');

        $mail = (new MailMessage)
            ->subject("Your {$planName} subscription is active")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$planName} {$sourceLabel} is now active.")
            ->line('Billing cycle: '.ucfirst((string) ($this->subscription->billing_cycle ?? 'n/a')).'.');

        if ($endsAt) {
            $mail->line("Access is currently available through {$endsAt}.");
        }

        return $mail
            ->action('View Billing', route('billing.index'))
            ->salutation('Best regards, '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_activated',
            'plan' => $this->subscription->plan?->name,
            'source' => $this->subscription->source,
            'message' => 'Your subscription is now active.',
            'action' => route('billing.index'),
        ];
    }
}
