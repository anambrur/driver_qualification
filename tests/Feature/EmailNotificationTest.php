<?php

use App\Mail\DriverComplianceDigestMail;
use App\Mail\DriverHiredMail;
use App\Mail\DriverRejectedMail;
use App\Notifications\SubscriptionActivatedNotification;
use App\Services\Billing\SubscriptionNotificationService;
use App\Services\Billing\TrialActivationService;
use App\Services\Driver\DriverHireService;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Database\Factories\SubscriptionFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

// Billing is per company (07): the Owner (and billing_email) receives the notifications.

test('trial activation sends subscription confirmation notification once', function () {
    Notification::fake();
    Billing::trialPlan();
    $company = Actors::company(subscribed: false);

    $subscription = app(TrialActivationService::class)->activate($company, $company->owner);

    Notification::assertSentTo($company->owner, SubscriptionActivatedNotification::class, function ($notification) use ($subscription) {
        return $notification->subscription->is($subscription);
    });

    app(SubscriptionNotificationService::class)->sendActivated($subscription);

    Notification::assertSentToTimes($company->owner, SubscriptionActivatedNotification::class, 1);
});

test('admin grant subscription sends confirmation notification', function () {
    Notification::fake();
    $company = Actors::company(subscribed: false);
    $subscription = SubscriptionFactory::new()->forCompany($company)->create(['source' => 'admin']);

    $sent = app(SubscriptionNotificationService::class)->sendActivated($subscription);

    expect($sent)->toBeTrue();
    Notification::assertSentTo($company->owner, SubscriptionActivatedNotification::class);
});

test('hiring a driver queues hired email', function () {
    Mail::fake();
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->status('pending')->create(['email' => 'pat@test.com']);

    app(TenantContext::class)->run($company->id, fn () => app(DriverHireService::class)->hire($driver, [
        'hire_date' => now()->toDateString(),
        'hazmat' => 'no',
        'lcv_certificate' => 'no',
    ], $company->owner_user_id));

    Mail::assertQueued(DriverHiredMail::class, function (DriverHiredMail $mail) use ($driver) {
        return $mail->driver->id === $driver->id;
    });
});

test('rejecting a driver queues rejected email', function () {
    Mail::fake();
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->status('pending')->create(['email' => 'sam@test.com']);

    app(TenantContext::class)->run($company->id, fn () => app(DriverHireService::class)->reject($driver, [
        'rejection_reason' => 'not_good_fit',
        'record_date' => now()->toDateString(),
    ], $company->owner_user_id));

    Mail::assertQueued(DriverRejectedMail::class);
});

test('compliance digest command queues digest for drivers with missing documents', function () {
    Mail::fake();
    $company = Actors::company();
    DocumentTypeFactory::new()->module('driver')->create(['name' => 'Medical Card']);
    DriverFactory::new()->forCompany($company)->status('active')->create(['email' => 'alex@test.com']);

    Artisan::call('compliance:send-digest-reminders');

    Mail::assertQueued(DriverComplianceDigestMail::class);
});
