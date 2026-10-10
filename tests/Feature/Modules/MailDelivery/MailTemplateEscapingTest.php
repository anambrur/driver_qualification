<?php

use App\Mail\ApplicationSubmittedMail;
use App\Mail\DriverComplianceDigestMail;
use App\Mail\DriverComplianceReminderMail;
use App\Mail\DriverHiredMail;
use App\Mail\DriverRejectedMail;
use App\Mail\VehicleComplianceStatusReminderMail;
use Database\Factories\CompanyFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/*
| Module 21 `mail-delivery`, MAIL-01: tenant/applicant-controlled strings (driver names,
| company names, document type names, asset labels, rejection reasons) must be escaped
| in every HTML template and must not break out of the Subject header.
*/

const MAIL_XSS = '<script>alert(1)</script><img src=x onerror=alert(2)>';

/**
 * @return array<string, Mailable>
 */
function mailDeliveryHostileMailables(): array
{
    $company = CompanyFactory::new()->create(['company_name' => 'Evil '.MAIL_XSS]);
    $driver = DriverFactory::new()->forCompany($company)->create([
        'first_name' => 'Eve'.MAIL_XSS,
        'hire_date' => now()->toDateString(),
    ]);
    $documentType = DocumentTypeFactory::new()->create(['name' => 'Doc'.MAIL_XSS]);
    $company = $company->company_name;

    return [
        'application-submitted' => new ApplicationSubmittedMail($driver, $company),
        'hired' => new DriverHiredMail($driver, $company),
        'rejected' => new DriverRejectedMail($driver, 'Reason'.MAIL_XSS, $company),
        'digest' => new DriverComplianceDigestMail($driver, [
            ['name' => 'Issue'.MAIL_XSS, 'status' => 'missing', 'label' => 'Label'.MAIL_XSS, 'expiry_date' => MAIL_XSS],
        ], $company),
        'driver-reminder' => new DriverComplianceReminderMail(
            $driver, $documentType, 'expiring', 'Label'.MAIL_XSS, MAIL_XSS, 3, $company,
        ),
        'vehicle-reminder' => new VehicleComplianceStatusReminderMail(
            $driver, $documentType, 'vehicle', 'Truck'.MAIL_XSS, 'expired', 'Label'.MAIL_XSS, MAIL_XSS, null, $company,
        ),
    ];
}

test('MAIL-01: every HTML mail template escapes tenant and applicant controlled strings', function () {
    foreach (mailDeliveryHostileMailables() as $name => $mailable) {
        $html = $mailable->render();

        expect($html)
            ->not->toContain('<script>', "{$name} renders raw <script>")
            ->not->toContain('<img src=x', "{$name} renders raw <img>")
            ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    }
});

test('MAIL-01: CRLF in a document type name cannot inject headers through the subject', function () {
    $company = CompanyFactory::new()->create();
    $driver = DriverFactory::new()->forCompany($company)->create();
    $documentType = DocumentTypeFactory::new()->create([
        'name' => "Medical\r\nBcc: attacker@evil.test",
    ]);

    Mail::to($driver->email)->send(new DriverComplianceReminderMail(
        $driver, $documentType, 'missing', 'Missing', null, null, $company->company_name,
    ));

    $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last();
    expect($sent)->not->toBeNull();

    $email = $sent->getOriginalMessage();
    $headers = $email->getHeaders()->toString();

    // Symfony encodes the CRLF into the folded Subject; no standalone Bcc header appears.
    expect($email->getBcc())->toBe([])
        ->and($email->getHeaders()->has('Bcc'))->toBeFalse()
        ->and($headers)->not->toMatch('/^Bcc:/mi');
});
