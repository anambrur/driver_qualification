<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vehicle;
use Database\Factories\DriverFactory;
use Database\Factories\SubscriptionFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Feature\Modules\PublicApplication\Applicant;
use Tests\Feature\Modules\SubscriptionLifecycle\SubscriptionLifecycle;
use Tests\Support\Actors;

/*
| WP8c (task 0.11): every event 08 §10 lists as "always audited" writes an audit row, and the
| rows are append-only. Platform, role, user, plan and support-session events are covered by
| their own module tests.
*/

function auditRow(string $action): AuditLog
{
    return AuditLog::query()->where('action', $action)->sole();
}

describe('tenant authentication', function () {
    it('audits a login and a logout', function () {
        $owner = Actors::companyOwner();

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect();
        $this->post('/logout')->assertRedirect('/');

        foreach (['auth.login', 'auth.logout'] as $action) {
            expect(auditRow($action))
                ->actor_type->toBe('user')
                ->actor_id->toBe($owner->id)
                ->company_id->toBe($owner->company_id);
        }
    });

    it('audits a failed login against the account\'s company, and an unknown email without one', function () {
        $owner = Actors::companyOwner();

        $this->post('/login', ['email' => $owner->email, 'password' => 'wrong-password']);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

        $rows = AuditLog::query()->where('action', 'auth.login_failed')->orderBy('id')->get();
        expect($rows)->toHaveCount(2)
            ->and($rows[0])->actor_id->toBe($owner->id)->company_id->toBe($owner->company_id)
            ->and($rows[0]->changes)->toEqual(['email' => $owner->email])
            ->and($rows[1])->actor_id->toBeNull()->company_id->toBeNull();
    });

    it('audits a password reset and a password change, never the password', function () {
        $owner = Actors::companyOwner();

        $this->post('/reset-password', [
            'token' => Password::broker()->createToken($owner),
            'email' => $owner->email,
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner->fresh())->put('/password', [
            'current_password' => 'new-password-1',
            'password' => 'new-password-2',
            'password_confirmation' => 'new-password-2',
        ])->assertSessionHasNoErrors();

        foreach (['auth.password_reset', 'auth.password_changed'] as $action) {
            $row = auditRow($action);
            expect($row)->actor_id->toBe($owner->id)->company_id->toBe($owner->company_id)
                ->and(json_encode($row->changes))->not->toContain('new-password');
        }
    });
});

describe('users and company', function () {
    it('audits a self-signup as the company being created by its new owner', function () {
        $this->post('/register', [
            'name' => 'Test User',
            'company_name' => 'Test Company',
            'email' => 'signup@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $owner = User::query()->where('email', 'signup@example.com')->sole();
        expect(auditRow('company.created'))
            ->actor_id->toBe($owner->id)
            ->company_id->toBe($owner->company_id)
            ->subject_id->toBe($owner->company_id)
            ->changes->toEqual(['source' => 'signup']);
    });

    it('audits a user changing their own email', function () {
        $owner = Actors::companyOwner();
        $from = $owner->email;

        $this->actingAs($owner)->patch('/admin/profile', ['name' => $owner->name, 'email' => 'changed@example.com'])
            ->assertSessionHasNoErrors();

        expect(auditRow('user.email_changed'))
            ->actor_id->toBe($owner->id)
            ->subject_id->toBe($owner->id)
            ->changes->toEqual(['from' => $from, 'to' => 'changed@example.com']);
    });

    it('audits an owner closing the account as a user deactivation and a company status change', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)->delete('/admin/profile', ['password' => 'password'])->assertRedirect('/');

        expect(auditRow('user.deactivated'))->actor_id->toBe($owner->id)->subject_id->toBe($owner->id)
            ->and(auditRow('company.status_changed'))->subject_id->toBe($owner->company_id)
            ->changes->toEqual(['from' => 'active', 'to' => 'deactivated', 'reason' => 'Closed by the owner']);
    });
});

describe('subscriptions', function () {
    it('audits a tenant cancelling and resuming the subscription', function () {
        // A local row: never reaches Stripe
        $company = Actors::company(subscribed: false);
        $subscription = SubscriptionFactory::new()->forCompany($company)->create([
            'source' => 'admin', 'stripe_subscription_id' => null, 'amount' => 0, 'ends_at' => now()->addDays(30),
        ]);

        $this->actingAs($company->owner)->post(route('billing.cancel'));
        $this->actingAs($company->owner)->post(route('billing.resume'));

        foreach (['subscription.cancel_requested', 'subscription.resumed'] as $action) {
            expect(auditRow($action))
                ->actor_type->toBe('user')
                ->actor_id->toBe($company->owner->id)
                ->company_id->toBe($company->id)
                ->subject_id->toBe($subscription->id);
        }
    });

    it('audits a local trial expiring as a system change', function () {
        Notification::fake();
        $sms = [];
        SubscriptionLifecycle::fakeVonage($sms);
        $company = Actors::company(subscribed: false);
        $trial = SubscriptionFactory::new()->forCompany($company)->trial()->create([
            'trial_ends_at' => now()->subDay(),
            'current_period_end' => now()->subDay(),
            'ends_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:send-expiry-reminders')->assertSuccessful();

        expect(auditRow('subscription.expired'))
            ->actor_type->toBe('system')
            ->company_id->toBe($company->id)
            ->subject_id->toBe($trial->id);
    });

    it('audits the open row a new Stripe subscription replaces', function () {
        Notification::fake();
        Billing::trialPlan(14);
        $company = Actors::company(subscribed: false);
        $this->actingAs($company->owner)->post(route('billing.trial'));
        $trial = $company->subscriptions()->sole();

        $stripeSub = Billing::stripeSubscription($company, Billing::price());
        Billing::fakeStripe($stripeSub);
        Billing::postWebhook($this, 'customer.subscription.created', $stripeSub)->assertOk();

        $row = auditRow('subscription.replaced');
        expect($row)->actor_type->toBe('stripe')->company_id->toBe($company->id)->subject_id->toBe($trial->id)
            ->and($row->changes['replaced_by'])->toBe($stripeSub['id']);
    });
});

describe('driver decisions', function () {
    it('audits a hire and a reject, without the free-text notes', function () {
        Mail::fake();
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $hired = DriverFactory::new()->forCompany($company)->status('pending')->create();
        $rejected = DriverFactory::new()->forCompany($company)->status('pending')->create();

        $this->actingAs($owner)->postJson(route('admin.driver.hire-status', $hired), [
            'action' => 'hire', 'hire_date' => now()->toDateString(), 'hazmat' => 'no', 'lcv_certificate' => 'no',
        ])->assertOk();
        $this->actingAs($owner)->postJson(route('admin.driver.hire-status', $rejected), [
            'action' => 'reject', 'rejection_reason' => 'mvr_issues', 'additional_info' => 'Private note', 'record_date' => now()->toDateString(),
        ])->assertOk();

        expect(auditRow('driver.hired'))->actor_id->toBe($owner->id)->company_id->toBe($company->id)->subject_id->toBe($hired->id)
            ->and(auditRow('driver.rejected'))->subject_id->toBe($rejected->id)
            ->changes->toEqual(['rejection_reason' => 'mvr_issues']);
    });

    it('audits an applicant withdrawing as a system change', function () {
        Applicant::fakeOtp();
        $me = Applicant::draft();

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.withdraw', ['slug' => $me->company->slug, 'driver_id' => $me->id]));

        expect(auditRow('driver.withdrawn'))
            ->actor_type->toBe('system')
            ->company_id->toBe($me->company_id)
            ->subject_id->toBe($me->id);
    });
});

describe('deletes, restores and downloads', function () {
    it('audits a vehicle delete and restore by a tenant user', function () {
        $owner = Actors::companyOwner();
        $vehicle = VehicleFactory::new()->forCompany(Actors::companyOf($owner))->create();

        $this->actingAs($owner)->deleteJson(route('admin.vehicle.destroy', $vehicle->id))->assertOk();
        $this->actingAs($owner)->postJson(route('admin.vehicle.restore', $vehicle->id))->assertOk();

        foreach (['vehicle.deleted', 'vehicle.restored'] as $action) {
            expect(auditRow($action))
                ->actor_type->toBe('user')
                ->actor_id->toBe($owner->id)
                ->company_id->toBe($vehicle->company_id)
                ->subject_type->toBe((new Vehicle)->getMorphClass())
                ->subject_id->toBe($vehicle->id);
        }
    });

    it('attributes a delete inside a support session to the staff member', function () {
        $company = Actors::company();
        $vehicle = VehicleFactory::new()->forCompany($company)->create();
        $session = Actors::inSupportSession($company);

        $this->deleteJson(route('admin.vehicle.destroy', $vehicle->id))->assertOk();

        expect(auditRow('vehicle.deleted'))
            ->actor_type->toBe('platform_user')
            ->actor_id->toBe($session->platform_user_id)
            ->support_session_id->toBe($session->id);
    });

    it('attributes a delete to the request\'s own guard when both guards are logged in', function () {
        $owner = Actors::companyOwner();
        $vehicle = VehicleFactory::new()->forCompany(Actors::companyOf($owner))->create();
        $staff = Actors::platformOwner();
        Actors::actingAsTenant($owner);
        $this->actingAs($staff, 'platform'); // as on a /platform route

        $vehicle->delete();

        expect(auditRow('vehicle.deleted'))->actor_type->toBe('platform_user')->actor_id->toBe($staff->id);
    });

    it('audits a DQ-document download', function () {
        Storage::fake('local');
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType(['name' => 'Medical Card']));

        $this->actingAs($owner)->get(route('admin.compliance.driver.documents.download', $document->id))->assertOk();

        expect(auditRow('document.downloaded'))
            ->actor_id->toBe($owner->id)
            ->company_id->toBe($owner->company_id)
            ->subject_id->toBe($document->id)
            ->changes->toEqual(['document_type' => 'Medical Card']);
    });
});

it('refuses to update or delete an audit row', function () {
    $row = AuditLog::recordSystem(null, 'test.event');

    expect(fn () => $row->update(['action' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class)
        ->and($row->fresh()->action)->toBe('test.event');
});
