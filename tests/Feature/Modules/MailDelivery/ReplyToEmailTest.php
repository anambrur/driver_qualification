<?php

use App\Mail\DemoRequestReceivedMail;
use App\Mail\DriverHiredMail;
use App\Models\SiteSetting;
use Database\Factories\DemoRequestFactory;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\Support\Actors;

/*
| Every mail leaves from the platform address. Company mail carries the company's name and exactly one
| Reply-To: its confirmed email, else its owner's verified login, else the platform's.
*/

beforeEach(function () {
    Config::set('mail.from.address', 'noreply@platform.test');
    Config::set('mail.from.name', 'DriverQ');
    Config::set('mail.reply_to.address', null);
    SiteSetting::getSettings()->update(['mail_from_name' => null, 'reply_to_email' => 'help@platform.test']);
});

function sentReplyMessage(callable $send): Email
{
    $send();

    /** @var Email */
    return app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
}

/**
 * @param  list<Address>  $addresses
 * @return list<string>
 */
function replyAddresses(array $addresses): array
{
    return array_map(fn (Address $address) => $address->getAddress(), $addresses);
}

function hiredMailFor(array $companyAttributes = []): DriverHiredMail
{
    $company = Actors::company(array_merge(['company_name' => 'Acme Trucking'], $companyAttributes));

    return new DriverHiredMail(DriverFactory::new()->forCompany($company)->create(['email' => 'driver@test.com']));
}

it('sends company mail from the platform address under the company name', function () {
    $message = sentReplyMessage(fn () => Mail::to('driver@test.com')->sendNow(hiredMailFor()));

    expect($message->getFrom()[0]->getAddress())->toBe('noreply@platform.test')
        ->and($message->getFrom()[0]->getName())->toBe('Acme Trucking via DriverQ');
});

it('sends company replies only to the confirmed company email', function () {
    $message = sentReplyMessage(fn () => Mail::to('driver@test.com')->sendNow(hiredMailFor(['reply_to_email' => 'office@acme.test'])));

    expect(replyAddresses($message->getReplyTo()))->toBe(['office@acme.test']);
});

it('sends company replies to the verified owner while no company email is confirmed', function () {
    $mail = hiredMailFor(['pending_reply_to_email' => 'office@acme.test']);
    $owner = $mail->driver->company->owner;

    $message = sentReplyMessage(fn () => Mail::to('driver@test.com')->sendNow($mail));

    expect(replyAddresses($message->getReplyTo()))->toBe([$owner->email]);
});

it('sends company replies to the platform when the owner is not verified', function () {
    $mail = hiredMailFor();
    $mail->driver->company->owner->forceFill(['email_verified_at' => null])->save();

    $message = sentReplyMessage(fn () => Mail::to('driver@test.com')->sendNow($mail));

    expect(replyAddresses($message->getReplyTo()))->toBe(['help@platform.test']);
});

it('gives platform mail the platform reply email', function () {
    $message = sentReplyMessage(fn () => Mail::raw('Hello', fn ($mail) => $mail->to('someone@test.com')));

    expect(replyAddresses($message->getReplyTo()))->toBe(['help@platform.test']);
});

it('falls back to the configured reply address when the platform has none', function () {
    SiteSetting::getSettings()->update(['reply_to_email' => null]);
    Config::set('mail.reply_to.address', 'env@platform.test');

    $message = sentReplyMessage(fn () => Mail::raw('Hello', fn ($mail) => $mail->to('someone@test.com')));

    expect(replyAddresses($message->getReplyTo()))->toBe(['env@platform.test']);
});

it('keeps the requester as the only reply address of a demo request', function () {
    $demo = DemoRequestFactory::new()->create(['email' => 'lead@prospect.test']);

    $message = sentReplyMessage(fn () => Mail::to('sales@platform.test')->sendNow(new DemoRequestReceivedMail($demo)));

    expect(replyAddresses($message->getReplyTo()))->toBe(['lead@prospect.test']);
});
