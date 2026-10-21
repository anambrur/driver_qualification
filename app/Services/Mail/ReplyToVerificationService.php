<?php

namespace App\Services\Mail;

use App\Mail\ReplyToVerificationMail;
use App\Models\Company;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A company (or the platform) names the inbox its replies go to. The address only takes over once
 * someone opens the link sent to it; until then the previous one keeps working.
 */
class ReplyToVerificationService
{
    public const LINK_HOURS = 48;

    public function request(Company|SiteSetting $owner, string $email): void
    {
        $email = Str::lower(trim($email));

        if ($email === $owner->reply_to_email) {
            $owner->update(['pending_reply_to_email' => null]);

            return;
        }

        $owner->update(['pending_reply_to_email' => $email]);

        Mail::to($email)->queue(new ReplyToVerificationMail(
            $this->verifyUrl($owner, $email),
            $owner instanceof Company ? $owner->company_name : (string) settings('site_name', config('app.name')),
        ));
    }

    /**
     * The link carries a hash of the address it was sent to, so a link to an address that was since
     * replaced does nothing. Opening the link again after it worked still reports success.
     */
    public function confirm(Company|SiteSetting $owner, string $hash): bool
    {
        $pending = $owner->pending_reply_to_email;

        if ($pending && hash_equals(sha1($pending), $hash)) {
            $owner->update(['reply_to_email' => $pending, 'pending_reply_to_email' => null]);

            return true;
        }

        return $owner->reply_to_email && hash_equals(sha1($owner->reply_to_email), $hash);
    }

    public function remove(Company|SiteSetting $owner): void
    {
        $owner->update(['reply_to_email' => null, 'pending_reply_to_email' => null]);
    }

    private function verifyUrl(Company|SiteSetting $owner, string $email): string
    {
        $expires = now()->addHours(self::LINK_HOURS);

        return $owner instanceof Company
            ? URL::temporarySignedRoute('reply-email.verify.company', $expires, ['company' => $owner->id, 'hash' => sha1($email)])
            : URL::temporarySignedRoute('reply-email.verify.platform', $expires, ['hash' => sha1($email)]);
    }
}
