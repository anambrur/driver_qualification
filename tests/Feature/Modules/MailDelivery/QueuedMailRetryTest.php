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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Log;

/*
| Module 21 `mail-delivery`, MAIL-02: every mailable is ShouldQueue. Without tries/backoff a
| transient SMTP error fails the job on the first attempt (queue:work defaults to --tries=1),
| and nothing records which mail to which driver was lost.
*/

/**
 * @return array<string, Mailable>
 */
function mailDeliveryQueuedMailables(): array
{
    $company = CompanyFactory::new()->create();
    $driver = DriverFactory::new()->forCompany($company)->create();
    $documentType = DocumentTypeFactory::new()->create();

    return [
        ApplicationSubmittedMail::class => new ApplicationSubmittedMail($driver),
        DriverHiredMail::class => new DriverHiredMail($driver),
        DriverRejectedMail::class => new DriverRejectedMail($driver, 'Reason'),
        DriverComplianceDigestMail::class => new DriverComplianceDigestMail($driver, []),
        DriverComplianceReminderMail::class => new DriverComplianceReminderMail(
            $driver, $documentType, 'missing', 'Missing', null, null, null,
        ),
        VehicleComplianceStatusReminderMail::class => new VehicleComplianceStatusReminderMail(
            $driver, $documentType, 'vehicle', 'Truck 1', 'missing', 'Missing', null, null, null,
        ),
    ];
}

test('MAIL-02: queued mail jobs retry with backoff', function () {
    foreach (mailDeliveryQueuedMailables() as $class => $mailable) {
        expect($mailable)->toBeInstanceOf(ShouldQueue::class);

        $job = new SendQueuedMailable($mailable);

        expect($job->tries)->toBeGreaterThan(1, "{$class} has no retries")
            ->and($job->backoff())->not->toBeNull("{$class} has no backoff");
    }
});

test('MAIL-02: a permanently failed queued mail is logged with its mailable and driver', function () {
    foreach (mailDeliveryQueuedMailables() as $class => $mailable) {
        Log::spy();

        (new SendQueuedMailable($mailable))->failed(new RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($class, $mailable) {
            return str_contains($message, 'mail')
                && ($context['mailable'] ?? null) === $class
                && ($context['driver_id'] ?? null) === $mailable->driver->id
                && ! array_key_exists('email', $context);
        })->once();
    }
});
