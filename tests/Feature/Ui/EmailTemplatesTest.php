<?php

use App\Mail\ApplicationSubmittedMail;
use App\Mail\CompanyInvitationMail;
use App\Mail\DriverComplianceDigestMail;
use App\Mail\DriverComplianceReminderMail;
use App\Mail\DriverHiredMail;
use App\Mail\DriverRejectedMail;
use App\Mail\VehicleComplianceStatusReminderMail;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\AssetTransferredNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringSoonNotification;
use App\Notifications\SupportSessionNotification;
use App\Services\Users\InvitationService;
use App\Support\Ui\Theme;
use App\Tenancy\TenantContext;
use Database\Factories\AssetTransferFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Actors;

/*
| UI-17 (phase 2, batch 8c): every email carries the platform brand colour and (when mail clients
| can show it) logo from Platform → Settings → Appearance. The 7 Mailables share
| emails/layouts/compliance; the MailMessage notifications use the published markdown mail
| components in resources/views/vendor/mail. Subjects, recipients, queues and text versions are
| unchanged.
*/

beforeEach(function () {
    Mail::fake();
    $this->owner = Actors::companyOwner();
    $this->company = Actors::companyOf($this->owner);
});

/**
 * @return array<string, Mailable>
 */
function brandedMailables(User $owner): array
{
    $company = Actors::companyOf($owner);
    $driver = DriverFactory::new()->forCompany($company)->create(['first_name' => 'Jordan', 'hire_date' => now()->toDateString()]);
    $type = DocumentTypeFactory::new()->create();
    $roleId = (int) Role::query()->where('team_id', $company->id)->where('name', 'Dispatcher')->value('id');
    $invitation = app(TenantContext::class)->run($company->id, fn () => app(InvitationService::class)->invite($owner, 'new.hire@acme.test', $roleId));

    return [
        'application-submitted' => new ApplicationSubmittedMail($driver, 'Acme Freight'),
        'hired' => new DriverHiredMail($driver, 'Acme Freight'),
        'rejected' => new DriverRejectedMail($driver, 'License class does not match', 'Acme Freight'),
        'digest' => new DriverComplianceDigestMail($driver, [['name' => 'Road Test', 'status' => 'missing', 'label' => 'Missing', 'expiry_date' => null]], 'Acme Freight'),
        'driver-reminder' => new DriverComplianceReminderMail($driver, $type, 'expiring', 'Expiring soon', 'Oct 29, 2026', 10, 'Acme Freight'),
        'vehicle-reminder' => new VehicleComplianceStatusReminderMail($driver, $type, 'vehicle', '#V-101', 'expired', 'Expired', 'Oct 1, 2026', null, 'Acme Freight'),
        'invitation' => new CompanyInvitationMail($invitation, str_repeat('a', 64)),
    ];
}

/**
 * @return array<string, Notification>
 */
function brandedNotifications(User $owner): array
{
    $company = Actors::companyOf($owner);
    $subscription = Actors::openSubscription($company);
    $transfer = AssetTransferFactory::new()->create();

    return [
        'payment-failed' => new PaymentFailedNotification($subscription, 'https://billing.example/invoice'),
        'subscription-activated' => new SubscriptionActivatedNotification($subscription),
        'subscription-expired' => new SubscriptionExpiredNotification($subscription),
        'subscription-expiring' => new SubscriptionExpiringSoonNotification($subscription, 5),
        'asset-transferred' => new AssetTransferredNotification($transfer->id, $transfer->source_company_id),
        'support-requested' => new SupportSessionNotification(Actors::inSupportSession($company)->id, 'requested'),
    ];
}

function brandSettings(array $attributes): void
{
    SiteSetting::getSettings()->update($attributes);
}

it('renders every Mailable on the branded layout with its text version', function () {
    foreach (brandedMailables($this->owner) as $name => $mail) {
        $html = $mail->render();
        $text = view($mail->content()->text, $mail->buildViewData())->render();

        expect($html)->toContain('background-color:'.Theme::DEFAULT_BRAND)
            ->toContain('<meta name="color-scheme" content="light">')
            ->toContain('@media only screen and (max-width: 600px)')
            ->not->toContain('#2563eb')
            ->not->toContain('background:#465fff;color:#ffffff')
            ->and(trim($text))->not->toBe('')
            ->and($text)->not->toContain('<html')->toContain(config('app.name'));
    }
});

it('labels each email for what it is', function () {
    $mailables = brandedMailables($this->owner);

    expect($mailables['invitation']->render())->toContain('Team invitation')->not->toContain('Compliance Notification')
        ->and($mailables['hired']->render())->toContain('Application update')
        ->and($mailables['rejected']->render())->toContain('Application update')
        ->and($mailables['application-submitted']->render())->toContain('Application update')
        ->and($mailables['driver-reminder']->render())->toContain('Compliance Notification')
        ->and($mailables['digest']->render())->toContain('Compliance Notification');
});

it('renders every notification on the published, branded mail theme with its text version', function () {
    foreach (brandedNotifications($this->owner) as $name => $notification) {
        $message = $notification->toMail($this->owner);
        $html = (string) $message->render();
        $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());

        expect($html)->toMatch('~class="email-pad" style="[^"]*background-color: '.Theme::DEFAULT_BRAND.'; padding: 24px 32px~')
            ->toContain('border-radius: 12px')
            ->toContain(config('app.name'))
            ->and($text)->not->toContain('<html')->toContain('Hello '.$this->owner->name);
    }
});

it('takes the brand colour from the site settings', function () {
    brandSettings(['primary_color' => '#0f766e']);

    $invitation = brandedMailables($this->owner)['invitation']->render();
    $payment = (string) brandedNotifications($this->owner)['payment-failed']->toMail($this->owner)->render();

    expect($invitation)->toContain('background-color:#0f766e;padding:24px 32px')
        ->toMatch('~<a href="[^"]+/invitations/a{64}"[^>]*background-color:#0f766e~')
        ->not->toContain(Theme::DEFAULT_BRAND)
        ->and($payment)->toContain('background-color: #0f766e; padding: 24px 32px')
        ->toMatch('~<a href="https://billing.example/invoice" class="button"[^>]*background-color: #0f766e~')
        ->not->toContain(Theme::DEFAULT_BRAND);
});

it('shows a png, jpg or gif logo with the site name as its alt text, and no webp logo', function () {
    brandSettings(['logo' => 'logos/acme.png']);
    $hired = brandedMailables($this->owner)['hired']->render();
    $payment = (string) brandedNotifications($this->owner)['payment-failed']->toMail($this->owner)->render();

    expect($hired)->toContain('<img src="'.asset('storage/logos/acme.png').'" alt="'.config('app.name').'"')
        ->and($payment)->toContain('<img src="'.asset('storage/logos/acme.png').'" alt="'.config('app.name').'"');

    brandSettings(['logo' => 'logos/acme.webp']);

    expect(brandedMailables($this->owner)['hired']->render())->not->toContain('<img');
});

it('gives Laravel\'s own reset password email the same branded theme', function () {
    $html = (string) (new ResetPassword('token'))->toMail($this->owner)->render();

    expect($html)->toMatch('~class="email-pad" style="[^"]*background-color: '.Theme::DEFAULT_BRAND.'; padding: 24px 32px~')
        ->toContain('Reset Password');
});
