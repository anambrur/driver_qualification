<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedDelivery;
use App\Models\Driver;
use App\Services\Mail\MailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;

#[WithoutRelations]
class DriverRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesQueuedDelivery, SerializesModels;

    public function __construct(
        public Driver $driver,
        public ?string $reasonLabel = null,
        public ?string $companyName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: app(MailIdentity::class)->from($this->driver->company),
            replyTo: app(MailIdentity::class)->replyTo($this->driver->company),
            subject: 'Update on your driver application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.driver.rejected',
            text: 'emails.driver.rejected-text',
            with: [
                'driver' => $this->driver,
                'reasonLabel' => $this->reasonLabel,
                'companyName' => $this->companyName ?? $this->driver->company?->company_name,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
