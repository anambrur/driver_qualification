<?php

use App\Models\ServiceDocument;
use App\Tenancy\TenantContext;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| MNT-02: the DataTables raw columns concatenated vehicle fields (unit no, make, model, VIN),
| category names and the schedule title into HTML unescaped. Vehicle fields come from any
| tenant user, and a super-admin's list shows every tenant's rows. The modals did the same in
| JavaScript: category names, uploaded file names and the vehicle dropdown went into .html()/
| .append() as strings.
*/

const MNT_PAYLOAD = '<img src=x onerror=alert(1)>';

function mntVehicleFields(): array
{
    return ['unit_no' => MNT_PAYLOAD, 'make' => MNT_PAYLOAD.'m', 'model' => MNT_PAYLOAD.'o', 'vin' => MNT_PAYLOAD.'v'];
}

describe('MNT-02: the list escapes tenant-controlled data', function () {
    it('escapes vehicle fields and category names in the service log list', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(mntVehicleFields());
        $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();
        $log->maintenanceCategories()->attach(Maintenance::category(['name' => MNT_PAYLOAD.'c']));
        $file = Files::stored($log->company_id);
        app(TenantContext::class)->run($log->company_id, fn () => ServiceDocument::create(['service_log_id' => $log->id, 'file_id' => $file->id]));

        $row = $this->actingAs($owner)->withHeaders(Maintenance::ajax())
            ->get(route('admin.service-log.index', ['draw' => 1]))
            ->assertOk()->json('data.0');

        $html = $row['vehicle_info'].$row['service_info'];
        expect($html)->not->toContain('<img')
            ->and($row['vehicle_info'])->toContain(e(MNT_PAYLOAD))
            ->and($row['vehicle_info'])->toContain(e(MNT_PAYLOAD.'v'))
            ->and($row['vehicle_info'])->toContain('fa-paperclip')
            ->and($row['service_info'])->toContain(e(MNT_PAYLOAD.'c'));
    });

    it('escapes the title, category and vehicle fields in the schedule list', function (bool $withTitle) {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(mntVehicleFields());
        MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create([
            'title' => $withTitle ? MNT_PAYLOAD.'t' : null,
            'maintenance_category_id' => Maintenance::category(['name' => MNT_PAYLOAD.'c'])->id,
        ]);

        $row = $this->actingAs($owner)->withHeaders(Maintenance::ajax())
            ->get(route('admin.maintenance-schedule.index', ['draw' => 1]))
            ->assertOk()->json('data.0');

        expect($row['schedule_info'].$row['vehicle_info'].$row['status'])->not->toContain('<img')
            ->and($row['schedule_info'])->toContain($withTitle ? e(MNT_PAYLOAD.'t') : e(MNT_PAYLOAD.'c'))
            ->and($row['vehicle_info'])->toContain(e(MNT_PAYLOAD))
            ->and($row['vehicle_info'])->toContain(e(MNT_PAYLOAD.'o'));
    })->with(['title' => true, 'category name' => false]);

    it('escapes the HTML helpers on the models', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(mntVehicleFields());
        Actors::actingAsTenant($owner);
        $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();
        $log->maintenanceCategories()->attach(Maintenance::category(['name' => MNT_PAYLOAD]));
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create();

        expect($log->fresh()->categories_html)->not->toContain('<img')
            ->and($schedule->fresh()->vehicle_info)->not->toContain('<img')
            ->and($schedule->status_badge)->toContain('Active');
    });
});

describe('MNT-02: the modals build HTML from escaped values', function () {
    it('escapes category names, file names and vehicle options on the service log page', function () {
        [$owner] = Maintenance::tenantWithVehicle();

        $html = $this->actingAs($owner)->get(route('admin.service-log.index'))->assertOk()->getContent();

        foreach (['>${category.name}<', '>${doc.original_name}<', '>${file.name}<', '>${message}<', 'cat.name +', "'\">' + vehicle.unit_no"] as $sink) {
            expect($html)->not->toContain($sink);
        }
        expect($html)->toContain('function escapeHtml(')
            ->and($html)->toContain('escapeHtml(doc.original_name)')
            ->and($html)->toContain('new Option(');
    });

    it('builds the schedule dropdowns with text options', function () {
        [$owner] = Maintenance::tenantWithVehicle();

        $html = $this->actingAs($owner)->get(route('admin.maintenance-schedule.index'))->assertOk()->getContent();

        expect($html)->not->toContain("'\">' + vehicle.unit_no")
            ->and($html)->not->toContain("category.name + '</option>'")
            ->and($html)->not->toContain('>${message}<')
            ->and($html)->toContain('new Option(');
    });

    it('escapes vehicle and category names in the server-rendered filters', function (string $route) {
        [$owner] = Maintenance::tenantWithVehicle(['unit_no' => MNT_PAYLOAD]);
        Maintenance::category(['name' => MNT_PAYLOAD.'c']);

        $html = $this->actingAs($owner)->get(route($route))->assertOk()->getContent();

        expect($html)->not->toContain(MNT_PAYLOAD)->and($html)->toContain(e(MNT_PAYLOAD));
    })->with(['admin.service-log.index', 'admin.maintenance-schedule.index']);
});
