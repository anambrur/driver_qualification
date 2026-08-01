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

class VehicleComplianceStatusReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Driver $driver,
        public DocumentType $documentType,
        public string $assetType,
        public string $assetLabel,
        public string $complianceStatus,
        public string $statusLabel,
        public ?string $expiryDate,
        public ?int $daysUntilExpiry,
        public ?string $companyName,
    ) {}

    public function envelope(): Envelope
    {
        $assetTypeLabel = $this->assetType === 'trailer' ? 'Trailer' : 'Vehicle';

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name'),
            ),
            subject: "{$assetTypeLabel} Compliance Reminder: {$this->documentType->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.compliance.vehicle-reminder',
            text: 'emails.compliance.vehicle-reminder-text',
            with: [
                'driver' => $this->driver,
                'documentType' => $this->documentType,
                'assetType' => $this->assetType,
                'assetLabel' => $this->assetLabel,
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
