<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Support\Actors;

/*
| UI-08 (phase 2, batch 3c): maintenance schedules and service logs on server-side tables. The
| old service log form sent files as new_documents[] on create too, which store() never reads,
| so documents picked while logging a service were dropped.
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

dataset('maintenance pages', [
    'schedules' => ['admin.maintenance-schedule.index', 'schedules', 'schedule-view-dialog'],
    'service logs' => ['admin.service-log.index', 'service-logs', 'service-log-view-dialog'],
]);

it('renders the page on the shared table without the legacy DataTables', function (string $route, string $table, string $view) {
    [$owner] = Maintenance::tenantWithVehicle();

    $this->actingAs($owner)->get(route($route))
        ->assertOk()
        ->assertSee("id=\"$table\"", false)
        ->assertSee("id=\"$view\"", false)
        ->assertDontSee('cdn.datatables.net', false)
        ->assertDontSee('onclick="view', false);
})->with('maintenance pages');

it('names the file input documents[] for a new service log, which store() saves', function () {
    [$owner, $vehicle] = Maintenance::tenantWithVehicle();

    $html = $this->actingAs($owner)->get(route('admin.service-log.index'))->assertOk()->getContent();
    expect($html)->toContain('name="documents[]"')
        ->and($html)->toContain("fileInput.name = record.id ? 'new_documents[]' : 'documents[]'");

    $this->post(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, [
        'documents' => [Maintenance::pdf()],
    ]), Maintenance::ajax())->assertOk();

    expect(DB::table('service_documents')->count())->toBe(1);
});

it('filters service logs by date on the server and ignores a malformed date', function () {
    [$owner, $vehicle] = Maintenance::tenantWithVehicle();
    $this->actingAs($owner);
    foreach (['2026-01-10', '2026-03-10'] as $date) {
        $this->post(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['service_date' => $date]), Maintenance::ajax())->assertOk();
    }

    $count = fn (array $filters) => $this->withHeaders(Maintenance::ajax())
        ->get(route('admin.service-log.index', ['draw' => 1] + $filters))->assertOk()->json('recordsFiltered');

    expect($count(['date_from' => '2026-02-01']))->toBe(1)
        ->and($count(['date_to' => '2026-02-01']))->toBe(1)
        ->and($count(['date_from' => 'yesterday']))->toBe(2);
});

it('hides edit and delete from staff who can only view', function (string $route, string $permission) {
    [$owner, $vehicle] = Maintenance::tenantWithVehicle();
    $this->actingAs($owner)->post($route === 'admin.service-log.index' ? route('admin.service-log.store') : route('admin.maintenance-schedule.store'),
        $route === 'admin.service-log.index' ? Maintenance::serviceLogPayload($vehicle) : Maintenance::schedulePayload($vehicle), Maintenance::ajax())->assertOk();

    $viewer = Actors::memberWithPermissions(Actors::companyOf($owner), [$permission]);
    $action = $this->actingAs($viewer)->withHeaders(Maintenance::ajax())->get(route($route, ['draw' => 1]))->assertOk()->json('data.0.action');

    expect($action)->toContain('data-action="view"')
        ->not->toContain('data-action="edit"')
        ->not->toContain('data-action="delete"');
})->with([
    'service logs' => ['admin.service-log.index', 'maintenance.view'],
    'schedules' => ['admin.maintenance-schedule.index', 'scheduled.view'],
]);
