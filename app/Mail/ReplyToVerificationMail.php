<?php

namespace App\Mail;

use App\Services\Mail\MailIdentity;
use App\Services\Mail\ReplyToVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReplyToVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $url,
        public string $ownerName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: app(MailIdentity::class)->from(),
            subject: 'Confirm the email for replies',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.settings.reply-to-verify',
            text: 'emails.settings.reply-to-verify-text',
            with: [
                'ownerName' => $this->ownerName,
                'url' => $this->url,
                'hours' => ReplyToVerificationService::LINK_HOURS,
            ],
        );
    }
}
