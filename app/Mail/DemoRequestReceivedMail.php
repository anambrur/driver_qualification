<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the platform's support inbox about a new homepage demo request or Contact page message.
 */
class DemoRequestReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public DemoRequest $demoRequest) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            replyTo: [new Address($this->demoRequest->email, $this->demoRequest->name)],
            subject: ($this->demoRequest->source === DemoRequest::SOURCE_CONTACT ? 'New contact message from ' : 'New demo request from ').$this->demoRequest->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.platform.demo-request',
            text: 'emails.platform.demo-request-text',
            with: ['demoRequest' => $this->demoRequest],
        );
    }
}
