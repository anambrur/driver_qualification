<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public ?string $hostedInvoiceUrl = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your plan';

        $mail = (new MailMessage)
            ->subject('Payment failed for your subscription')
            ->greeting("Hello {$notifiable->name},")
            ->line("We could not process the payment for your {$planName} subscription.")
            ->line('Please update your payment method to avoid losing access.');

        if ($this->hostedInvoiceUrl) {
            $mail->action('Pay Invoice', $this->hostedInvoiceUrl);
        } else {
            $mail->action('Manage Billing', route('billing.index'));
        }

        return $mail->salutation('Best regards, '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'plan' => $this->subscription->plan?->name,
            'message' => 'Your subscription payment failed. Please update your payment method.',
            'action' => $this->hostedInvoiceUrl ?: route('billing.index'),
        ];
    }
}
