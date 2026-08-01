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

class DriverComplianceDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, status: string, label: string, expiry_date: ?string}>  $issues
     */
    public function __construct(
        public Driver $driver,
        public array $issues,
        public ?string $companyName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name'),
            ),
            subject: 'Compliance reminder: documents need your attention',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.compliance.driver-digest',
            text: 'emails.compliance.driver-digest-text',
            with: [
                'driver' => $this->driver,
                'issues' => $this->issues,
                'companyName' => $this->companyName ?? $this->driver->company?->company_name,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
