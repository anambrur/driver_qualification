<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedDelivery;
use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name'),
            ),
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
