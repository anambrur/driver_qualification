<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\BillingRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivatedNotification extends Notification implements ShouldQueue
{
    use BillingRecipient, Queueable;

    public function __construct(public Subscription $subscription) {}

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->planName();
        $sourceLabel = match ($this->subscription->source) {
            'trial' => 'free trial',
            'admin' => 'complimentary subscription',
            default => 'subscription',
        };
        $endsAt = $this->subscription->accessEndsAt()?->format('F j, Y');

        $mail = (new MailMessage)
            ->subject("Your {$planName} subscription is active")
            ->greeting($this->greetingFor($notifiable))
            ->line("Your {$planName} {$sourceLabel} is now active.")
            ->line('Billing cycle: '.($this->subscription->interval === 'year' ? 'Yearly' : 'Monthly').'.');

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
            'plan' => $this->subscription->planName(),
            'source' => $this->subscription->source,
            'message' => 'Your subscription is now active.',
            'action' => route('billing.index'),
        ];
    }
}
