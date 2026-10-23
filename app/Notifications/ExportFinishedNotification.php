<?php

namespace App\Notifications;

use App\Models\Export;
use App\Services\Mail\MailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the requester an export is ready to download, or that it failed (req 2.5). Carries ids
 * only; the export is read when the message is built.
 */
class ExportFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $exportId, public int $companyId)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $export = $this->export();
        $identity = app(MailIdentity::class);
        $from = $identity->from($export->company);
        $mail = (new MailMessage)->from($from->address, $from->name);

        foreach ($identity->replyTo($export->company) as $address) {
            $mail->replyTo($address->address, $address->name);
        }

        $mail->greeting('Hello '.$notifiable->name.',');

        return ($export->status === 'ready'
            ? $mail->subject("Your {$export->typeLabel()} is ready")
                ->line("The {$export->typeLabel()} you requested is ready to download until {$export->expires_at?->format('M d, Y')}.")
                ->action('Open exports', route('exports.index'))
            : $mail->subject("Your {$export->typeLabel()} could not be built")
                ->line((string) $export->error)
                ->action('Open exports', route('exports.index')))
            ->salutation('Best regards, '.config('app.name'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $export = $this->export();

        return [
            'export_id' => $export->id,
            'type' => $export->type,
            'status' => $export->status,
            'error' => $export->error,
            'url' => route('exports.index'),
        ];
    }

    private function export(): Export
    {
        return Export::withoutTenancy()->with('company')->where('company_id', $this->companyId)->findOrFail($this->exportId);
    }
}
