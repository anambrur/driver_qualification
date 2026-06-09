<?php

use App\Mail\DriverComplianceReminderMail;
use App\Mail\VehicleComplianceStatusReminderMail;
use App\Models\AssetGroup;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ComplianceReminderService;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function createSuperAdminUser(): User
{
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $user->assignRole('super-admin');

    return $user;
}

function createCompanyForUser(User $user): Company
{
    return Company::create([
        'user_id' => $user->id,
        'company_name' => 'Test Transport LLC',
        'slug' => 'test-transport',
        'email' => 'fleet@test.com',
        'status' => 'active',
    ]);
}

function createDriverForCompany(Company $company, array $overrides = []): Driver
{
    $driverUser = User::factory()->create();

    return Driver::create(array_merge([
        'user_id' => $driverUser->id,
        'company_id' => $company->id,
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'email' => 'jane.driver@test.com',
        'status' => 'active',
    ], $overrides));
}

test('driver compliance reminder endpoint queues email', function () {
    Mail::fake();

    $admin = createSuperAdminUser();
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
    $admin = createSuperAdminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.compliance.driver.documents.send-reminder'), []);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('driver compliance reminder fails when driver has no email', function () {
    Mail::fake();

    $admin = createSuperAdminUser();
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

    $admin = createSuperAdminUser();
    $company = createCompanyForUser($admin);
    $driver = createDriverForCompany($company);

    $vehicle = Vehicle::create([
        'company_id' => $company->id,
        'unit_no' => 'TRK-100',
        'vin' => '1HGBH41JXMN109186',
        'year' => 2022,
        'make' => 'Freightliner',
        'model' => 'Cascadia',
    ]);

    AssetGroup::create([
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

    $admin = createSuperAdminUser();
    $company = createCompanyForUser($admin);
    $driver = createDriverForCompany($company);

    $documentType = DocumentType::create([
        'name' => 'MVR',
        'module' => 'driver',
        'status' => true,
    ]);

    $service = app(ComplianceReminderService::class);
    $result = $service->sendDriverReminder($driver, $documentType);

    expect($result['success'])->toBeTrue();

    Mail::assertQueued(DriverComplianceReminderMail::class, function (DriverComplianceReminderMail $mail) {
        return $mail->statusLabel === 'Missing';
    });
});
