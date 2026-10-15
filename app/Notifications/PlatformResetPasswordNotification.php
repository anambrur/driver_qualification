<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * The stock reset mail, with a link to the platform reset form. Also sent to new platform staff
 * and owners, who set their first password through it.
 */
class PlatformResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return route('platform.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
