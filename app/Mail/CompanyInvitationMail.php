<?php

namespace App\Mail;

use App\Models\CompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  string  $token  plaintext; only its hash is stored
     */
    public function __construct(
        public CompanyInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to join '.$this->invitation->company->company_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.users.invitation',
            text: 'emails.users.invitation-text',
            with: [
                'companyName' => $this->invitation->company->company_name,
                'roleName' => $this->invitation->role->name,
                'url' => route('invitations.show', $this->token),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
