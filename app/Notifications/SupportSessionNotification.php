<?php

namespace App\Notifications;

use App\Models\PlatformSupportSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the company Owner that platform support asked for access, started or ended a session
 * (04 §4.3). Carries ids only; the session is read when the mail is built.
 */
class SupportSessionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const EVENTS = ['requested', 'started', 'ended'];

    public function __construct(public int $sessionId, public string $event)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $session = PlatformSupportSession::query()->with('requester:id,name')->findOrFail($this->sessionId);
        $staff = $session->requester->name ?? 'Platform support';
        $access = $session->scope === 'read_write' ? 'view and change' : 'view';

        $mail = (new MailMessage)->greeting('Hello '.$notifiable->name.',');

        $mail = match ($this->event) {
            'requested' => $mail->subject('Platform support asks to access your account')
                ->line("{$staff} asks to {$access} your company's records.")
                ->line('Reason: '.$session->reason)
                ->line('Nothing happens until you approve. You can refuse, and you can end an approved session at any time.')
                ->action('Review the request', route('support-access.index')),
            'started' => $mail->subject('A support session started on your account')
                ->line("{$staff} started a support session. It ends at {$session->expires_at?->format('M j, Y g:i A T')} at the latest.")
                ->action('End it now', route('support-access.index')),
            default => $mail->subject('A support session on your account ended')
                ->line("The support session by {$staff} has ended ({$session->end_reason})."),
        };

        return $mail->salutation('Best regards, '.config('app.name'));
    }
}
