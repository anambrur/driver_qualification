<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells every Platform Owner that a company was purged from the platform area, or that the
 * purge failed. The company is gone, so its name and the counts travel with the message.
 */
class CompanyPurgedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, int>  $deleted
     */
    public function __construct(
        public int $companyId,
        public string $companyName,
        public string $actorName,
        public string $reason,
        public array $deleted,
        public ?string $error,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Hello '.$notifiable->name.',');

        if ($this->error !== null) {
            return $mail->subject("Purge of {$this->companyName} failed")
                ->line("{$this->actorName} started a purge of {$this->companyName} (#{$this->companyId}). It failed and nothing was deleted.")
                ->line('Error: '.$this->error)
                ->action('Open the company', route('platform.companies.show', $this->companyId))
                ->salutation('Best regards, '.config('app.name'));
        }

        $mail->subject("{$this->companyName} was purged")
            ->line("{$this->actorName} permanently deleted {$this->companyName} (#{$this->companyId}).")
            ->line('Reason: '.$this->reason);

        foreach ($this->deleted as $table => $rows) {
            $mail->line("{$table}: {$rows}");
        }

        return $mail->line('The audit log keeps this record.')->salutation('Best regards, '.config('app.name'));
    }
}
