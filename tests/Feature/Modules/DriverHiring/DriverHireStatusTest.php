<?php

use App\Mail\DriverHiredMail;
use App\Mail\DriverRejectedMail;
use App\Models\Driver;
use App\Services\Driver\DriverHireService;
use Database\Factories\DriverFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Actors;

/*
| Module 17 `driver-hiring`: POST admin.driver.hire-status (permission:drivers.hire)
| -> DriverController::updateHireStatus -> DriverHireService::{hire,reject}.
*/

function hiringTenantWithDriver(array $attributes = ['status' => 'pending']): array
{
    $owner = Actors::companyOwner();
    $driver = DriverFactory::new()->forCompany(Actors::companyOf($owner))
        ->status(Arr::pull($attributes, 'status', 'pending'))
        ->create($attributes);

    return [$owner, $driver];
}

function hirePayload(array $overrides = []): array
{
    return array_merge([
        'action' => 'hire',
        'hire_date' => now()->toDateString(),
        'hazmat' => 'no',
        'lcv_certificate' => 'yes',
    ], $overrides);
}

function rejectPayload(array $overrides = []): array
{
    return array_merge([
        'action' => 'reject',
        'rejection_reason' => 'mvr_issues',
        'additional_info' => 'Too many violations.',
        'record_date' => now()->toDateString(),
    ], $overrides);
}

describe('HIRE-01: the pending check is atomic with the status change', function () {
    it('does not hire twice when two requests hold the same stale pending driver', function () {
        Mail::fake();
        [$owner, $driver] = hiringTenantWithDriver();
        Actors::actingAsTenant($owner);
        $first = Driver::withoutTenancy()->findOrFail($driver->id);
        $second = Driver::withoutTenancy()->findOrFail($driver->id); // loaded before the first request commits

        $service = app(DriverHireService::class);
        $service->hire($first, hirePayload(), $owner->id);

        expect(fn () => $service->hire($second, hirePayload(), $owner->id))
            ->toThrow(InvalidArgumentException::class);

        Mail::assertQueued(DriverHiredMail::class, 1);
    });

    it('does not flip a hired driver to rejected from a stale request', function () {
        Mail::fake();
        [$owner, $driver] = hiringTenantWithDriver();
        Actors::actingAsTenant($owner);
        $first = Driver::withoutTenancy()->findOrFail($driver->id);
        $second = Driver::withoutTenancy()->findOrFail($driver->id);

        $service = app(DriverHireService::class);
        $service->hire($first, hirePayload(), $owner->id);

        expect(fn () => $service->reject($second, rejectPayload(), $owner->id))
            ->toThrow(InvalidArgumentException::class);

        expect($driver->fresh())->status->toBe('active')->rejection_reason->toBeNull();
        Mail::assertQueued(DriverHiredMail::class, 1);
        Mail::assertNotQueued(DriverRejectedMail::class);
    });

    it('rejects a double-submit over HTTP with 422 and one email', function () {
        Mail::fake();
        [$owner, $driver] = hiringTenantWithDriver();

        $this->actingAs($owner)->postJson(route('admin.driver.hire-status', $driver->id), hirePayload())->assertOk();
        $this->actingAs($owner)->postJson(route('admin.driver.hire-status', $driver->id), hirePayload())->assertStatus(422);

        Mail::assertQueued(DriverHiredMail::class, 1);
    });
});

describe('HIRE-02: validator rejection reasons match the drivers.rejection_reason enum', function () {
    it('accepts and stores every enum reason', function (string $reason) {
        Mail::fake();
        [$owner, $driver] = hiringTenantWithDriver();

        $this->actingAs($owner)
            ->postJson(route('admin.driver.hire-status', $driver->id), rejectPayload(['rejection_reason' => $reason]))
            ->assertOk()
            ->assertJsonPath('success', true);

        expect($driver->fresh())->status->toBe('rejected')->rejection_reason->toBe($reason);
        Mail::assertQueued(DriverRejectedMail::class, 1);
    })->with(['not_good_fit', 'failed_drug_test', 'background_check_issues', 'cdl_issues', 'mvr_issues', 'psp_issues', 'other']);

    it('returns 422 for a reason outside the enum', function () {
        Mail::fake();
        [$owner, $driver] = hiringTenantWithDriver();

        $this->actingAs($owner)
            ->postJson(route('admin.driver.hire-status', $driver->id), rejectPayload(['rejection_reason' => 'made_up']))
            ->assertStatus(422);

        expect($driver->fresh()->status)->toBe('pending');
        Mail::assertNothingQueued();
    });

    it('has a human label for every enum reason', function (string $reason) {
        expect(app(DriverHireService::class)->getRejectionReasonLabel($reason))->not->toBe(ucfirst($reason));
    })->with(['not_good_fit', 'failed_drug_test', 'background_check_issues', 'cdl_issues', 'mvr_issues', 'psp_issues', 'other']);
});

describe('HIRE-03: hire/reject is tenant-scoped and permission-gated', function () {
    it('returns 404 when another tenant hires or rejects my driver', function (string $action) {
        Mail::fake();
        [, $driver] = hiringTenantWithDriver();
        $intruder = Actors::companyOwner();

        $payload = $action === 'hire' ? hirePayload() : rejectPayload();
        $this->actingAs($intruder)
            ->postJson(route('admin.driver.hire-status', $driver->id), $payload)
            ->assertNotFound();

        expect($driver->fresh())->status->toBe('pending')->decided_by_user_id->toBeNull();
        Mail::assertNothingQueued();
    })->with(['hire', 'reject']);

    it('returns 403 for a user of the company without drivers.hire', function () {
        Mail::fake();
        [$owner, $driver] = hiringTenantWithDriver();

        $this->actingAs(Actors::plainUser(Actors::companyOf($owner)))
            ->postJson(route('admin.driver.hire-status', $driver->id), hirePayload())
            ->assertForbidden();

        expect($driver->fresh()->status)->toBe('pending');
        Mail::assertNothingQueued();
    });

    it('lets the owning tenant hire and records the audit fields', function () {
        Mail::fake();
        [$owner, $driver] = hiringTenantWithDriver();

        $this->actingAs($owner)
            ->postJson(route('admin.driver.hire-status', $driver->id), hirePayload())
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        expect($driver->fresh())->status->toBe('active')->decided_by_user_id->toBe($owner->id)->hired_at->not->toBeNull();
        Mail::assertQueued(DriverHiredMail::class, fn ($m) => $m->hasTo($driver->email));
    });
});
