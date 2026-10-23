<?php

use App\Jobs\RunCompanyPurge;
use App\Models\AuditLog;
use App\Models\Company;
use App\Notifications\CompanyPurgedNotification;
use App\Services\Company\CompanyPurgeService;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Actors;

/*
| The platform purge button (decided 23 Oct 2026, no second approval): the Danger zone card on
| the company page, for platform.companies.purge only. Typed company name and a reason; the
| purge runs as a queued job with the same rules as companies:purge; Platform Owners are emailed.
*/

function purgeableNow(): Company
{
    $company = Actors::company([], subscribed: false);
    $company->forceFill(['status' => 'deactivated', 'status_changed_at' => now()->subYear()])->save();

    return $company->fresh();
}

it('shows the blockers and no button while the company cannot be purged', function () {
    $company = Actors::company();

    $this->actingAs(Actors::platformOwner(), 'platform')->get(route('platform.companies.show', $company))->assertOk()
        ->assertSee('Danger zone')->assertSee('The company is not deactivated.')
        ->assertDontSee('name="confirm_name"', false);
});

it('shows what will be deleted and the form when it can', function () {
    $company = purgeableNow();

    $this->actingAs(Actors::platformOwner(), 'platform')->get(route('platform.companies.show', $company))->assertOk()
        ->assertSee('What will be deleted')->assertSee('name="confirm_name"', false)
        ->assertSee(route('platform.companies.purge', $company), false);
});

it('hides the card and refuses the request without platform.companies.purge', function () {
    $company = purgeableNow();
    $admin = Actors::platformStaff('platform_admin');

    $this->actingAs($admin, 'platform')->get(route('platform.companies.show', $company))->assertOk()->assertDontSee('Danger zone');
    $this->actingAs($admin, 'platform')->post(route('platform.companies.purge', $company), ['confirm_name' => $company->company_name, 'reason' => 'x'])
        ->assertForbidden();
});

it('needs the exact company name and a reason', function () {
    Queue::fake();
    $company = purgeableNow();

    $this->actingAs(Actors::platformOwner(), 'platform')->from(route('platform.companies.show', $company))
        ->post(route('platform.companies.purge', $company), ['confirm_name' => strtoupper($company->company_name), 'reason' => ''])
        ->assertSessionHasErrors(['confirm_name', 'reason']);

    Queue::assertNothingPushed();
});

it('refuses a company that is still within its retention period', function () {
    Queue::fake();
    $company = Actors::company([], subscribed: false);
    DriverFactory::new()->forCompany($company)->create();
    $company->forceFill(['status' => 'deactivated', 'status_changed_at' => now()->subYear()])->save();

    $this->actingAs(Actors::platformOwner(), 'platform')->from(route('platform.companies.show', $company))
        ->post(route('platform.companies.purge', $company), ['confirm_name' => $company->company_name, 'reason' => 'Closed'])
        ->assertSessionHasErrors('purge');

    Queue::assertNothingPushed();
});

it('queues the purge and audits who started it and why', function () {
    Queue::fake();
    $company = purgeableNow();
    $staff = Actors::platformOwner();

    $this->actingAs($staff, 'platform')
        ->post(route('platform.companies.purge', $company), ['confirm_name' => $company->company_name, 'reason' => 'Retention passed, ticket 9'])
        ->assertRedirect(route('platform.companies.index'));

    Queue::assertPushed(RunCompanyPurge::class, fn (RunCompanyPurge $job) => $job->companyId === $company->id && $job->actorId === $staff->id);
    expect(AuditLog::query()->where('action', 'company.purge_started')->where('company_id', $company->id)->value('changes'))
        ->toBe(['reason' => 'Retention passed, ticket 9']);
});

it('purges in the job and emails every Platform Owner', function () {
    Notification::fake();
    $company = purgeableNow();
    $staff = Actors::platformOwner();
    $other = Actors::platformOwner();

    (new RunCompanyPurge($company->id, $company->company_name, $staff->id, 'Retention passed'))->handle(app(CompanyPurgeService::class));

    expect(Company::query()->whereKey($company->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'company.purged')->where('company_id', $company->id)->sole())
        ->actor_id->toBe($staff->id)
        ->changes->toHaveKey('reason', 'Retention passed');
    Notification::assertSentTo([$staff, $other], CompanyPurgedNotification::class, fn ($n) => $n->error === null && $n->deleted['users'] >= 1);
});

it('tells the owners when the purge fails', function () {
    Notification::fake();
    $staff = Actors::platformOwner();

    (new RunCompanyPurge(999, 'Gone Co', $staff->id, 'x'))->failed(new RuntimeException('boom'));

    Notification::assertSentTo($staff, CompanyPurgedNotification::class, fn ($n) => $n->error === 'boom');
});
