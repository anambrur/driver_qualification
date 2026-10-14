<?php

namespace App\Notifications\Concerns;

use App\Models\User;

/**
 * Billing notices go to the Owner (mail and database) and to companies.billing_email, an
 * on-demand mail route with no name and no database inbox (07 §9).
 */
trait BillingRecipient
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    protected function greetingFor(object $notifiable): string
    {
        return $notifiable instanceof User ? "Hello {$notifiable->name}," : 'Hello,';
    }
}
