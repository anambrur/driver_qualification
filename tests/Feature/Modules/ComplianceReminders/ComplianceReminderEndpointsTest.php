<?php

use App\Mail\DriverComplianceDigestMail;
use App\Mail\DriverComplianceReminderMail;
use App\Mail\VehicleComplianceStatusReminderMail;
use App\Services\ComplianceReminderService;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Actors;

/*
| Module 18 `compliance-reminders`:
|   POST admin.compliance.driver.documents.send-reminder (permission:drivers.edit)
|   POST admin.compliance.documents.send-reminder (permission:fleets.edit)
|   -> ComplianceReminderController -> ComplianceReminderService
|   artisan compliance:send-digest-reminders -> DriverComplianceDigestMail
*/

function remindersTenantWithDriver(): array
{
    $owner = Actors::companyOwner();
    $driver = DriverFactory::new()->forCompany(Actors::companyOf($owner))->create(['status' => 'active']);

    return [$owner, $driver];
}

describe('REM-01: reminder endpoints work for a subscribed tenant with permission', function () {
    it('queues a driver reminder for the tenant own driver', function () {
        Mail::fake();
        [$owner, $driver] = remindersTenantWithDriver();
        $type = DocumentTypeFactory::new()->module('driver')->create();

        $this->actingAs($owner)
            ->postJson(route('admin.compliance.driver.documents.send-reminder'), [
                'driver_id' => $driver->id,
                'document_type_id' => $type->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        Mail::assertQueued(DriverComplianceReminderMail::class, 1);
    });

    it('queues a vehicle reminder to the assigned driver', function () {
        Mail::fake();
        $owner = Actors::companyOwner();
        $group = AssetGroupFactory::new()->forCompany(Actors::companyOf($owner))->create();
        $type = DocumentTypeFactory::new()->module('vehicle')->create();

        $this->actingAs($owner)
            ->postJson(route('admin.compliance.documents.send-reminder'), [
                'asset_id' => $group->vehicle_id,
                'asset_type' => 'vehicle',
                'document_type_id' => $type->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        Mail::assertQueued(VehicleComplianceStatusReminderMail::class, 1);
    });

    it('rejects a user without drivers.edit', function () {
        Mail::fake();
        [, $driver] = remindersTenantWithDriver();
        $type = DocumentTypeFactory::new()->module('driver')->create();

        $this->actingAs(Actors::plainUser())
            ->postJson(route('admin.compliance.driver.documents.send-reminder'), [
                'driver_id' => $driver->id,
                'document_type_id' => $type->id,
            ])
            ->assertForbidden();

        Mail::assertNothingQueued();
    });

    it('does not let a tenant remind another company driver', function () {
        Mail::fake();
        [, $driver] = remindersTenantWithDriver();
        $type = DocumentTypeFactory::new()->module('driver')->create();

        $response = $this->actingAs(Actors::companyOwner())
            ->postJson(route('admin.compliance.driver.documents.send-reminder'), [
                'driver_id' => $driver->id,
                'document_type_id' => $type->id,
            ]);

        expect($response->status())->toBeIn([403, 404]);
        Mail::assertNothingQueued();
    });
});

describe('REM-02: send-reminder endpoints are rate limited', function () {
    it('throttles repeated driver reminders from one user', function () {
        Mail::fake();
        [$owner, $driver] = remindersTenantWithDriver();
        $type = DocumentTypeFactory::new()->module('driver')->create();
        $payload = ['driver_id' => $driver->id, 'document_type_id' => $type->id];

        $statuses = collect(range(1, 15))->map(fn () => $this->actingAs($owner)
            ->postJson(route('admin.compliance.driver.documents.send-reminder'), $payload)
            ->status());

        expect($statuses->contains(429))->toBeTrue();
        expect(count(Mail::queued(DriverComplianceReminderMail::class)))->toBeLessThanOrEqual(10);
    });

    it('throttles repeated vehicle reminders from one user', function () {
        Mail::fake();
        $owner = Actors::companyOwner();
        $group = AssetGroupFactory::new()->forCompany(Actors::companyOf($owner))->create();
        $type = DocumentTypeFactory::new()->module('vehicle')->create();
        $payload = ['asset_id' => $group->vehicle_id, 'asset_type' => 'vehicle', 'document_type_id' => $type->id];

        $statuses = collect(range(1, 15))->map(fn () => $this->actingAs($owner)
            ->postJson(route('admin.compliance.documents.send-reminder'), $payload)
            ->status());

        expect($statuses->contains(429))->toBeTrue();
        expect(count(Mail::queued(VehicleComplianceStatusReminderMail::class)))->toBeLessThanOrEqual(10);
    });
});

describe('REM-03: errors are not leaked and the digest is sent once per day', function () {
    it('does not return the exception message from the driver endpoint', function () {
        Log::spy();
        [$owner, $driver] = remindersTenantWithDriver();
        $type = DocumentTypeFactory::new()->module('driver')->create();
        $this->mock(ComplianceReminderService::class)
            ->shouldReceive('sendDriverReminder')
            ->andThrow(new RuntimeException('SQLSTATE[HY000] secret-internal-detail'));

        $response = $this->actingAs($owner)
            ->postJson(route('admin.compliance.driver.documents.send-reminder'), [
                'driver_id' => $driver->id,
                'document_type_id' => $type->id,
            ]);

        $response->assertStatus(500)->assertJson(['success' => false]);
        expect($response->getContent())->not->toContain('secret-internal-detail');
        Log::shouldHaveReceived('error')->once();
    });

    it('does not return the exception message from the vehicle endpoint', function () {
        Log::spy();
        $owner = Actors::companyOwner();
        $group = AssetGroupFactory::new()->forCompany(Actors::companyOf($owner))->create();
        $type = DocumentTypeFactory::new()->module('vehicle')->create();
        $this->mock(ComplianceReminderService::class)
            ->shouldReceive('sendVehicleReminder')
            ->andThrow(new RuntimeException('SQLSTATE[HY000] secret-internal-detail'));

        $response = $this->actingAs($owner)
            ->postJson(route('admin.compliance.documents.send-reminder'), [
                'asset_id' => $group->vehicle_id,
                'asset_type' => 'vehicle',
                'document_type_id' => $type->id,
            ]);

        $response->assertStatus(500)->assertJson(['success' => false]);
        expect($response->getContent())->not->toContain('secret-internal-detail');
        Log::shouldHaveReceived('error')->once();
    });

    it('sends at most one digest per driver per day when the command runs twice', function () {
        Mail::fake();
        remindersTenantWithDriver();
        DocumentTypeFactory::new()->module('driver')->create();

        $this->artisan('compliance:send-digest-reminders')->assertSuccessful();
        $this->artisan('compliance:send-digest-reminders')->assertSuccessful();

        Mail::assertQueued(DriverComplianceDigestMail::class, 1);
    });
});
