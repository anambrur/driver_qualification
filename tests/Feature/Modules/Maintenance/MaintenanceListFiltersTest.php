<?php

use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Support\Actors;

/*
| MNT-07 (found 2026-10-10): the service log list filtered by category with
| whereJsonContains('maintenance_categories', ...), but categories are a pivot table
| (service_log_category), not a JSON column. Picking a category in the filter made the
| query fail, and the DataTables error response carried the SQL error text to the browser.
*/

describe('MNT-07: the service log category filter', function () {
    it('lists only the logs in the chosen category', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $oil = Maintenance::category(['name' => 'Oil']);
        $tyres = Maintenance::category(['name' => 'Tyres']);
        $withOil = ServiceLogFactory::new()->forVehicle($vehicle)->create();
        $withOil->maintenanceCategories()->attach([$oil->id, $tyres->id]);
        ServiceLogFactory::new()->forVehicle($vehicle)->create()->maintenanceCategories()->attach($tyres);

        $response = $this->actingAs($owner)->withHeaders(Maintenance::ajax())
            ->get(route('admin.service-log.index', ['draw' => 1, 'category_id' => $oil->id]))
            ->assertOk();

        expect($response->json('error'))->toBeNull()
            ->and(collect($response->json('data'))->pluck('id')->all())->toBe([$withOil->id]);
    });
});

describe('MNT-07: list scoping (regression guards)', function () {
    it('lists only the tenant\'s own logs and schedules', function (string $route, string $factory) {
        [$owner, $mine] = Maintenance::tenantWithVehicle();
        [, $theirs] = Maintenance::tenantWithVehicle();
        $own = $factory::new()->forVehicle($mine)->create();
        $factory::new()->forVehicle($theirs)->create();

        $ids = collect($this->actingAs($owner)->withHeaders(Maintenance::ajax())
            ->get(route($route, ['draw' => 1]))->assertOk()->json('data'))->pluck('id')->all();

        expect($ids)->toBe([$own->id]);
    })->with([
        'service logs' => ['admin.service-log.index', ServiceLogFactory::class],
        'schedules' => ['admin.maintenance-schedule.index', MaintenanceScheduleFactory::class],
    ]);

    it('does not show another tenant\'s records by id', function () {
        [$owner] = Maintenance::tenantWithVehicle();
        [, $theirs] = Maintenance::tenantWithVehicle();
        $log = ServiceLogFactory::new()->forVehicle($theirs)->create();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($theirs)->create();

        $this->actingAs($owner)->getJson(route('admin.service-log.show', $log->id))->assertForbidden();
        $this->actingAs($owner)->getJson(route('admin.service-log.edit', $log->id))->assertForbidden();
        $this->actingAs($owner)->deleteJson(route('admin.service-log.destroy', $log->id))->assertForbidden();
        $this->actingAs($owner)->getJson(route('admin.maintenance-schedule.show', $schedule->id))->assertForbidden();
        $this->actingAs($owner)->deleteJson(route('admin.maintenance-schedule.destroy', $schedule->id))->assertForbidden();
        $this->actingAs($owner)->getJson(route('admin.service-log.get-vehicle-details', $theirs->id))->assertNotFound();

        expect($log->fresh())->not->toBeNull()->and($schedule->fresh())->not->toBeNull();
    });

    it('requires the maintenance permissions', function () {
        $user = Actors::plainUser();

        $this->actingAs($user)->get(route('admin.service-log.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.maintenance-schedule.index'))->assertForbidden();
    });
});

/*
| MNT-08 (found 2026-10-10): edit() didn't load the log's categories, so the edit form opened
| with every category unchecked and saving it failed "Please select at least one maintenance
| category" unless the user re-ticked them.
*/
it('MNT-08: sends the log\'s categories to the edit form', function () {
    [$owner, $vehicle] = Maintenance::tenantWithVehicle();
    $category = Maintenance::category();
    $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();
    $log->maintenanceCategories()->attach($category);

    $this->actingAs($owner)->getJson(route('admin.service-log.edit', $log->id))
        ->assertOk()
        ->assertJsonPath('data.maintenance_categories.0.id', $category->id);
});
