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
class DriverHiredMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesQueuedDelivery, SerializesModels;

    public function __construct(
        public Driver $driver,
        public ?string $companyName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: app(MailIdentity::class)->from($this->driver->company),
            replyTo: app(MailIdentity::class)->replyTo($this->driver->company),
            subject: 'Congratulations — you have been hired',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.driver.hired',
            text: 'emails.driver.hired-text',
            with: [
                'driver' => $this->driver,
                'companyName' => $this->companyName ?? $this->driver->company?->company_name,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
