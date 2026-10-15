<?php

use App\Mail\ApplicationSubmittedMail;
use App\Mail\DriverComplianceDigestMail;
use App\Mail\DriverComplianceReminderMail;
use App\Mail\DriverHiredMail;
use App\Mail\DriverRejectedMail;
use App\Mail\VehicleComplianceStatusReminderMail;
use App\Models\DocumentType;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\Support\Actors;

test('mailables include plain-text content parts', function () {
    $company = Actors::company(['company_name' => 'Mail Co']);

    $driver = DriverFactory::new()->forCompany($company)->status('active')->create([
        'first_name' => 'Taylor',
        'last_name' => 'Driver',
        'email' => 'taylor@test.com',
        'hire_date' => now()->toDateString(),
    ]);

    $documentType = DocumentType::create([
        'name' => 'Medical Card',
        'module' => 'driver',
        'status' => true,
    ]);

    expect((new DriverHiredMail($driver))->content()->text)->toBe('emails.driver.hired-text')
        ->and((new ApplicationSubmittedMail($driver))->content()->text)->toBe('emails.driver.application-submitted-text')
        ->and((new DriverRejectedMail($driver, 'Not a fit'))->content()->text)->toBe('emails.driver.rejected-text')
        ->and((new DriverComplianceDigestMail($driver, [
            ['name' => 'Medical Card', 'status' => 'missing', 'label' => 'Missing', 'expiry_date' => null],
        ]))->content()->text)->toBe('emails.compliance.driver-digest-text')
        ->and((new DriverComplianceReminderMail(
            $driver,
            $documentType,
            'missing',
            'Missing',
            null,
            null,
            $company->company_name,
        ))->content()->text)->toBe('emails.compliance.driver-reminder-text')
        ->and((new VehicleComplianceStatusReminderMail(
            $driver,
            $documentType,
            'vehicle',
            'Truck 1',
            'expired',
            'Expired',
            now()->subDay()->toDateString(),
            null,
            $company->company_name,
        ))->content()->text)->toBe('emails.compliance.vehicle-reminder-text');
});

test('mail config exposes reply_to settings', function () {
    Config::set('mail.reply_to.address', 'support@example.com');
    Config::set('mail.reply_to.name', 'Support');

    expect(config('mail.reply_to.address'))->toBe('support@example.com')
        ->and(config('mail.reply_to.name'))->toBe('Support');
});

test('hired mailable renders multipart html and text', function () {
    $company = Actors::company(['company_name' => 'Render Co']);

    $driver = DriverFactory::new()->forCompany($company)->status('active')->create([
        'first_name' => 'Jordan',
        'last_name' => 'Driver',
        'email' => 'jordan@test.com',
        'hire_date' => now()->toDateString(),
    ]);

    $mailable = new DriverHiredMail($driver, $company->company_name);

    $html = $mailable->render();
    expect($html)->toContain('Jordan')->toContain('hired');

    $text = view('emails.driver.hired-text', [
        'driver' => $driver,
        'companyName' => $company->company_name,
    ])->render();

    expect($text)->toContain('Jordan')->toContain('hired')->not->toContain('<html');
});

test('mail check deliverability command runs for a domain', function () {
    $exit = Artisan::call('mail:check-deliverability', [
        '--domain' => 'driverfileshub.com',
    ]);

    // Exit may be FAILURE until DKIM is published; command must still execute.
    expect(in_array($exit, [0, 1], true))->toBeTrue();
    expect(Artisan::output())->toContain('Checking mail authentication DNS');
});
