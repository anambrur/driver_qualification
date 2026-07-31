<?php

namespace App\Mail;

use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

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
            subject: 'We received your driver application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.driver.application-submitted',
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
