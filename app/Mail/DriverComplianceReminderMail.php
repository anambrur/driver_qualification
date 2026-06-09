<?php

namespace App\Mail;

use App\Models\DocumentType;
use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DriverComplianceReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Driver $driver,
        public DocumentType $documentType,
        public string $complianceStatus,
        public string $statusLabel,
        public ?string $expiryDate,
        public ?int $daysUntilExpiry,
        public ?string $companyName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name'),
            ),
            subject: 'Action Required: '.$this->documentType->name.' Compliance Reminder',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.compliance.driver-reminder',
            with: [
                'driver' => $this->driver,
                'documentType' => $this->documentType,
                'complianceStatus' => $this->complianceStatus,
                'statusLabel' => $this->statusLabel,
                'expiryDate' => $this->expiryDate,
                'daysUntilExpiry' => $this->daysUntilExpiry,
                'companyName' => $this->companyName,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
