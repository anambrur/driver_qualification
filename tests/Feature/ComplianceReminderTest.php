<?php

use App\Mail\DriverComplianceReminderMail;
use App\Mail\VehicleComplianceStatusReminderMail;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\User;
use App\Services\ComplianceReminderService;
use App\Tenancy\TenantContext;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Actors;

// Reminders are sent by the company's own staff (the platform acts only in a support session)
function createReminderUser(): User
{
    return Actors::companyOwner();
}

function createCompanyForUser(User $user): Company
{
    return Actors::companyOf($user);
}

function createDriverForCompany(Company $company, array $overrides = []): Driver
{
    return DriverFactory::new()->forCompany($company)->create(array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'email' => 'jane.driver@test.com',
    ], $overrides));
}

test('driver compliance reminder endpoint queues email', function () {
    Mail::fake();

    $admin = createReminderUser();
    $company = createCompanyForUser($admin);
    $driver = createDriverForCompany($company);

    $documentType = DocumentType::create([
        'name' => 'Medical Certificate',
        'module' => 'driver',
        'status' => true,
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.compliance.driver.documents.send-reminder'), [
        'driver_id' => $driver->id,
        'document_type_id' => $documentType->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    Mail::assertQueued(DriverComplianceReminderMail::class, function (DriverComplianceReminderMail $mail) use ($driver, $documentType) {
        return $mail->driver->is($driver)
            && $mail->documentType->is($documentType)
            && $mail->complianceStatus === 'missing';
    });
});

test('driver compliance reminder returns validation error for invalid payload', function () {
    $admin = createReminderUser();

    $response = $this->actingAs($admin)->postJson(route('admin.compliance.driver.documents.send-reminder'), []);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('driver compliance reminder fails when driver has no email', function () {
    Mail::fake();

    $admin = createReminderUser();
    $company = createCompanyForUser($admin);
    $driver = createDriverForCompany($company, ['email' => null]);

    $documentType = DocumentType::create([
        'name' => 'CDL',
        'module' => 'driver',
        'status' => true,
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.compliance.driver.documents.send-reminder'), [
        'driver_id' => $driver->id,
        'document_type_id' => $documentType->id,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
        ]);

    Mail::assertNothingQueued();
});

test('vehicle compliance reminder endpoint queues email for assigned driver', function () {
    Mail::fake();

    $admin = createReminderUser();
    $company = createCompanyForUser($admin);
    $driver = createDriverForCompany($company);

    $vehicle = VehicleFactory::new()->forCompany($company)->create(['unit_no' => 'TRK-100']);

    AssetGroupFactory::new()->create([
        'group_name' => 'Group A',
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'active',
    ]);

    $documentType = DocumentType::create([
        'name' => 'Annual Inspection',
        'module' => 'vehicle',
        'status' => true,
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.compliance.documents.send-reminder'), [
        'asset_id' => $vehicle->id,
        'document_type_id' => $documentType->id,
        'asset_type' => 'vehicle',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    Mail::assertQueued(VehicleComplianceStatusReminderMail::class, function (VehicleComplianceStatusReminderMail $mail) use ($driver, $documentType) {
        return $mail->driver->is($driver)
            && $mail->documentType->is($documentType)
            && $mail->assetType === 'vehicle';
    });
});

test('compliance reminder service resolves missing document status', function () {
    Mail::fake();

    $admin = createReminderUser();
    $company = createCompanyForUser($admin);
    $driver = createDriverForCompany($company);

    $documentType = DocumentType::create([
        'name' => 'MVR',
        'module' => 'driver',
        'status' => true,
    ]);

    $result = app(TenantContext::class)->run($company->id, fn () => app(ComplianceReminderService::class)->sendDriverReminder($driver, $documentType));

    expect($result['success'])->toBeTrue();

    Mail::assertQueued(DriverComplianceReminderMail::class, function (DriverComplianceReminderMail $mail) {
        return $mail->statusLabel === 'Missing';
    });
});
