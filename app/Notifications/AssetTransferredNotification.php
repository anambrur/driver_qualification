<?php

namespace App\Notifications;

use App\Models\AssetTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a company Owner that the platform moved a vehicle or trailer into or out of their
 * company (04 §7). Carries ids only; the transfer row is read when the mail is built.
 */
class AssetTransferredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $transferId, public int $companyId)
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
        $transfer = AssetTransfer::query()->with(['sourceCompany:id,company_name', 'targetCompany:id,company_name'])->findOrFail($this->transferId);
        $asset = $transfer->asset_type === 'vehicle' ? 'vehicle' : 'trailer';
        $outgoing = $transfer->source_company_id === $this->companyId;

        return (new MailMessage)
            ->subject($outgoing ? "A {$asset} was transferred out of your company" : "A {$asset} was transferred to your company")
            ->greeting('Hello '.$notifiable->name.',')
            ->line($outgoing
                ? "Platform support transferred one of your {$asset}s to {$transfer->targetCompany->company_name}. Its records stay in your account, read-only."
                : "Platform support transferred a {$asset} from {$transfer->sourceCompany->company_name} to your company, with its documents and maintenance history.")
            ->line('Reason: '.$transfer->reason)
            ->salutation('Best regards, '.config('app.name'));
    }
}
