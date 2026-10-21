<?php

namespace App\Services\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailables\Address;

/**
 * Who a mail is from and where replies go. Every mail leaves from the platform's own address (its
 * domain passes SPF/DKIM); a company only lends its name, and replies go to its own inbox.
 */
class MailIdentity
{
    public function from(?Company $company = null): Address
    {
        $name = $this->platformName();

        return new Address(
            config('mail.from.address'),
            $company ? "{$company->company_name} via {$name}" : $name,
        );
    }

    /**
     * The company's verified reply email, else its owner's verified login email, else the platform's.
     *
     * @return list<Address>
     */
    public function replyTo(?Company $company = null): array
    {
        if ($company?->reply_to_email) {
            return [new Address($company->reply_to_email, $company->company_name)];
        }

        $owner = $company?->owner;
        if ($owner && $owner->hasVerifiedEmail()) {
            return [new Address($owner->email, $company->company_name)];
        }

        $platform = $this->platformReplyTo();

        return $platform ? [$platform] : [];
    }

    public function platformReplyTo(): ?Address
    {
        $address = settings('reply_to_email') ?: config('mail.reply_to.address');

        if (! is_string($address) || $address === '') {
            return null;
        }

        return new Address($address, $this->platformName());
    }

    private function platformName(): string
    {
        return (string) (settings('mail_from_name') ?: config('mail.from.name'));
    }
}
