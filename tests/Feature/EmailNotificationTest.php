<?php

use App\Mail\DriverComplianceDigestMail;
use App\Mail\DriverHiredMail;
use App\Mail\DriverRejectedMail;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionActivatedNotification;
use App\Services\Billing\SubscriptionNotificationService;
use App\Services\Billing\TrialActivationService;
use App\Services\Driver\DriverHireService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function makeTrialPlan(): Plan
{
    return Plan::create([
        'name' => 'Free Trial',
        'slug' => 'free-trial-'.uniqid(),
        'price' => 0,
        'currency' => 'USD',
        'billing_cycle' => 'trial',
        'duration_days' => 14,
        'trial_days' => 14,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function makeMonthlyPlan(): Plan
{
    return Plan::create([
        'name' => 'Monthly',
        'slug' => 'monthly-'.uniqid(),
        'price' => 49,
        'currency' => 'USD',
        'billing_cycle' => 'monthly',
        'duration_days' => 30,
        'trial_days' => 0,
        'is_active' => true,
        'sort_order' => 2,
    ]);
}

test('trial activation sends subscription confirmation notification once', function () {
    Notification::fake();

    $user = User::factory()->create(['status' => 'active']);
    $plan = makeTrialPlan();

    $service = app(TrialActivationService::class);
    $subscription = $service->activate($user, $plan);

    Notification::assertSentTo($user, SubscriptionActivatedNotification::class, function ($notification) use ($subscription) {
        return $notification->subscription->is($subscription);
    });

    app(SubscriptionNotificationService::class)->sendActivated($subscription);

    Notification::assertSentToTimes($user, SubscriptionActivatedNotification::class, 1);
});

test('admin grant subscription sends confirmation notification', function () {
    Notification::fake();

    $user = User::factory()->create(['status' => 'active']);
    $plan = makeMonthlyPlan();
    $endsAt = now()->addDays(30);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'stripe_subscription_id' => null,
        'stripe_status' => 'active',
        'billing_cycle' => 'monthly',
        'amount' => $plan->price,
        'currency' => 'USD',
        'current_period_start' => now(),
        'current_period_end' => $endsAt,
        'cancel_at_period_end' => true,
        'ends_at' => $endsAt,
        'source' => 'admin',
    ]);

    $sent = app(SubscriptionNotificationService::class)->sendActivated($subscription);

    expect($sent)->toBeTrue();
    Notification::assertSentTo($user, SubscriptionActivatedNotification::class);
});

test('hiring a driver queues hired email', function () {
    Mail::fake();

    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'company_name' => 'Hire Co',
        'slug' => 'hire-co-'.uniqid(),
        'email' => 'hire@test.com',
        'status' => 'active',
    ]);

    $driver = Driver::create([
        'user_id' => User::factory()->create()->id,
        'company_id' => $company->id,
        'first_name' => 'Pat',
        'last_name' => 'Applicant',
        'email' => 'pat@test.com',
        'status' => 'pending',
    ]);

    app(DriverHireService::class)->hire($driver, [
        'hire_date' => now()->toDateString(),
        'hazmat' => 'no',
        'lcv_certificate' => 'no',
    ], $user->id);

    Mail::assertQueued(DriverHiredMail::class, function (DriverHiredMail $mail) use ($driver) {
        return $mail->driver->id === $driver->id;
    });
});

test('rejecting a driver queues rejected email', function () {
    Mail::fake();

    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'company_name' => 'Reject Co',
        'slug' => 'reject-co-'.uniqid(),
        'email' => 'reject@test.com',
        'status' => 'active',
    ]);

    $driver = Driver::create([
        'user_id' => User::factory()->create()->id,
        'company_id' => $company->id,
        'first_name' => 'Sam',
        'last_name' => 'Applicant',
        'email' => 'sam@test.com',
        'status' => 'pending',
    ]);

    app(DriverHireService::class)->reject($driver, [
        'rejection_reason' => 'not_good_fit',
        'record_date' => now()->toDateString(),
    ], $user->id);

    Mail::assertQueued(DriverRejectedMail::class);
});

test('compliance digest command queues digest for drivers with missing documents', function () {
    Mail::fake();

    $user = User::factory()->create();
    $company = Company::create([
        'user_id' => $user->id,
        'company_name' => 'Digest Co',
        'slug' => 'digest-co-'.uniqid(),
        'email' => 'digest@test.com',
        'status' => 'active',
    ]);

    DocumentType::create([
        'name' => 'Medical Card',
        'module' => 'driver',
        'status' => true,
    ]);

    Driver::create([
        'user_id' => User::factory()->create()->id,
        'company_id' => $company->id,
        'first_name' => 'Alex',
        'last_name' => 'Driver',
        'email' => 'alex@test.com',
        'status' => 'active',
    ]);

    Artisan::call('compliance:send-digest-reminders');

    Mail::assertQueued(DriverComplianceDigestMail::class);
});
