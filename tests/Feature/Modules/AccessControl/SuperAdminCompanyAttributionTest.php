<?php

use App\Models\MaintenanceCategory;
use App\Models\MaintenanceSchedule;
use App\Models\ServiceLog;
use App\Models\Vehicle;
use Tests\Support\Actors;

/*
| ACL-05: getAllUserCompanyId() returns the super-admin's own company, and the service-log and
| maintenance-schedule controllers saved that on create. A record a super-admin created for a
| tenant's vehicle was filed under the super-admin's company, so the tenant never saw it.
| Records attached to a vehicle now belong to the vehicle's company.
*/

function aclServiceLogPayload(Vehicle $vehicle): array
{
    return [
        'vehicle_id' => $vehicle->id,
        'service_date' => now()->toDateString(),
        'maintenance_categories' => [MaintenanceCategory::create(['name' => 'Oil change'])->id],
        'odometer_at_service' => 1000,
        'current_odometer' => 1000,
        'total_cost' => 50,
        'status' => 'completed',
    ];
}

function aclSchedulePayload(?Vehicle $vehicle): array
{
    return [
        'vehicle_id' => $vehicle?->id,
        'maintenance_category_id' => MaintenanceCategory::create(['name' => 'Inspection'])->id,
        'schedule_type' => 'date',
        'interval_days' => 30,
        'status' => 'active',
    ];
}

it('files a super-admin\'s service log under the vehicle\'s company', function () {
    $tenant = Actors::companyOwner();
    $vehicle = Vehicle::factory()->forCompany(Actors::companyOf($tenant))->create();

    $this->actingAs(Actors::superAdmin())
        ->postJson(route('admin.service-log.store'), aclServiceLogPayload($vehicle))
        ->assertOk();

    expect(ServiceLog::withoutTenancy()->sole()->company_id)->toBe($vehicle->company_id);
});

it('files a super-admin\'s maintenance schedule under the vehicle\'s company', function () {
    $tenant = Actors::companyOwner();
    $vehicle = Vehicle::factory()->forCompany(Actors::companyOf($tenant))->create();

    $this->actingAs(Actors::superAdmin())
        ->postJson(route('admin.maintenance-schedule.store'), aclSchedulePayload($vehicle))
        ->assertOk();

    expect(MaintenanceSchedule::withoutTenancy()->sole()->company_id)->toBe($vehicle->company_id);
});

it('files a super-admin\'s schedule with no vehicle under the company they pick (decided PO-07)', function () {
    $admin = Actors::superAdmin();
    $company = Actors::companyOf(Actors::companyOwner());

    $this->actingAs($admin)
        ->postJson(route('admin.maintenance-schedule.store'), aclSchedulePayload(null))
        ->assertStatus(422)->assertJsonValidationErrors('company_id', 'errors');

    $this->actingAs($admin)
        ->postJson(route('admin.maintenance-schedule.store'), aclSchedulePayload(null) + ['company_id' => $company->id])
        ->assertOk();

    expect(MaintenanceSchedule::withoutTenancy()->sole()->company_id)->toBe($company->id);
});

it('still files a tenant\'s records under the tenant\'s company', function () {
    $tenant = Actors::companyOwner();
    $company = Actors::companyOf($tenant);
    $vehicle = Vehicle::factory()->forCompany($company)->create();

    $this->actingAs($tenant)->postJson(route('admin.service-log.store'), aclServiceLogPayload($vehicle))->assertOk();
    $this->actingAs($tenant)->postJson(route('admin.maintenance-schedule.store'), aclSchedulePayload($vehicle))->assertOk();

    expect(ServiceLog::withoutTenancy()->sole()->company_id)->toBe($company->id)
        ->and(MaintenanceSchedule::withoutTenancy()->sole()->company_id)->toBe($company->id);
});
