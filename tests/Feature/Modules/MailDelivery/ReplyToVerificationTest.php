<?php

use App\Mail\ReplyToVerificationMail;
use App\Models\Company;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Support\Actors;

/*
| A company (or the platform) saves an email for replies. It only takes over once the link sent to it
| is opened; until then the previous one keeps working.
*/

beforeEach(function () {
    Mail::fake();
});

function companyVerifyUrl(Company $company, string $email): string
{
    return URL::temporarySignedRoute('reply-email.verify.company', now()->addHours(48), ['company' => $company->id, 'hash' => sha1($email)]);
}

it('renders the verification mail with its link in html and text', function () {
    $mail = new ReplyToVerificationMail('https://app.test/reply-email/verify/x', 'Acme Trucking');

    expect($mail->render())->toContain('Acme Trucking')->toContain('https://app.test/reply-email/verify/x')
        ->and(view($mail->content()->text, $mail->content()->with)->render())->toContain('https://app.test/reply-email/verify/x');
});

it('keeps the confirmed email and sends a link to the new one', function () {
    $company = Actors::company(['reply_to_email' => 'old@acme.test']);

    $this->actingAs($company->owner)
        ->post(route('admin.settings.company.reply-email.store'), ['reply_to_email' => 'Office@Acme.test'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($company->fresh())
        ->reply_to_email->toBe('old@acme.test')
        ->pending_reply_to_email->toBe('office@acme.test');
    Mail::assertQueued(ReplyToVerificationMail::class, fn ($mail) => $mail->hasTo('office@acme.test'));
});

it('rejects an invalid email', function () {
    $company = Actors::company();

    $this->actingAs($company->owner)
        ->post(route('admin.settings.company.reply-email.store'), ['reply_to_email' => 'not-an-email'])
        ->assertSessionHasErrors('reply_to_email');

    expect($company->fresh()->pending_reply_to_email)->toBeNull();
    Mail::assertNothingQueued();
});

it('does not let a user without company.edit change it', function () {
    $company = Actors::company();

    $this->actingAs(Actors::dispatcher($company))
        ->post(route('admin.settings.company.reply-email.store'), ['reply_to_email' => 'office@acme.test'])
        ->assertForbidden();

    expect($company->fresh()->pending_reply_to_email)->toBeNull();
});

it('only changes the signed-in user\'s own company', function () {
    $mine = Actors::company();
    $other = Actors::company(['reply_to_email' => 'other@other.test']);

    $this->actingAs($mine->owner)
        ->post(route('admin.settings.company.reply-email.store'), ['reply_to_email' => 'office@acme.test'])
        ->assertRedirect();
    $this->actingAs($mine->owner)
        ->delete(route('admin.settings.company.reply-email.destroy'))
        ->assertRedirect();

    expect($other->fresh())
        ->reply_to_email->toBe('other@other.test')
        ->pending_reply_to_email->toBeNull();
});

it('confirms the pending email from the signed link without signing in', function () {
    $company = Actors::company(['reply_to_email' => 'old@acme.test', 'pending_reply_to_email' => 'office@acme.test']);

    $this->get(companyVerifyUrl($company, 'office@acme.test'))
        ->assertOk()
        ->assertSee('Email confirmed')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($company->fresh())
        ->reply_to_email->toBe('office@acme.test')
        ->pending_reply_to_email->toBeNull();

    $this->get(companyVerifyUrl($company, 'office@acme.test'))->assertSee('Email confirmed');
});

it('refuses a tampered or expired link', function () {
    $company = Actors::company(['pending_reply_to_email' => 'office@acme.test']);

    $url = companyVerifyUrl($company, 'office@acme.test');

    $this->get($url.'x')->assertForbidden();
    $this->travel(49)->hours();
    $this->get($url)->assertForbidden();

    expect($company->fresh()->reply_to_email)->toBeNull();
});

it('ignores a link sent to an email that was replaced since', function () {
    $company = Actors::company(['pending_reply_to_email' => 'second@acme.test']);

    $this->get(companyVerifyUrl($company, 'first@acme.test'))
        ->assertOk()
        ->assertSee('Link no longer works');

    expect($company->fresh())
        ->reply_to_email->toBeNull()
        ->pending_reply_to_email->toBe('second@acme.test');
});

it('removes the company email for replies', function () {
    $company = Actors::company(['reply_to_email' => 'office@acme.test', 'pending_reply_to_email' => 'new@acme.test']);

    $this->actingAs($company->owner)
        ->delete(route('admin.settings.company.reply-email.destroy'))
        ->assertRedirect();

    expect($company->fresh())
        ->reply_to_email->toBeNull()
        ->pending_reply_to_email->toBeNull();
});

it('shows the status on the company settings page', function () {
    $company = Actors::company(['reply_to_email' => 'office@acme.test', 'pending_reply_to_email' => 'new@acme.test']);

    $this->actingAs($company->owner)
        ->get(route('admin.settings.company.edit', $company->id))
        ->assertOk()
        ->assertSee('Email for replies')
        ->assertSee('office@acme.test')
        ->assertSee('Waiting for confirmation');
});

it('lets the platform owner set and confirm the platform email for replies', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->post(route('platform.settings.site.reply-email.store'), ['reply_to_email' => 'help@platform.test'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Mail::assertQueued(ReplyToVerificationMail::class, fn ($mail) => $mail->hasTo('help@platform.test'));
    expect(SiteSetting::first()->pending_reply_to_email)->toBe('help@platform.test');

    $this->get(URL::temporarySignedRoute('reply-email.verify.platform', now()->addHour(), ['hash' => sha1('help@platform.test')]))
        ->assertOk()
        ->assertSee('Email confirmed');

    expect(SiteSetting::first())
        ->reply_to_email->toBe('help@platform.test')
        ->pending_reply_to_email->toBeNull();
});

it('keeps company users out of the platform email for replies', function () {
    $this->actingAs(Actors::companyOwner())
        ->post(route('platform.settings.site.reply-email.store'), ['reply_to_email' => 'evil@test.com'])
        ->assertRedirect();

    expect(SiteSetting::first()?->pending_reply_to_email)->toBeNull();
    Mail::assertNothingQueued();
});
