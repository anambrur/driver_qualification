<?php

use Database\Factories\AssetGroupFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| UI-07 (phase 2, batch 3b): vehicles, trailers and asset groups on server-side tables, with the
| add/edit form in a dialog and row actions shown only to staff who may use them.
*/

dataset('fleet pages', [
    'vehicles' => ['admin.vehicle.index', 'vehicles', 'vehicle-dialog'],
    'trailers' => ['admin.trailer.index', 'trailers', 'trailer-dialog'],
    'asset groups' => ['admin.asset-group.index', 'asset-groups', 'asset-group-dialog'],
]);

it('renders the page on the shared table without the legacy DataTables', function (string $route, string $table, string $dialog) {
    $this->actingAs(Actors::companyOwner())->get(route($route))
        ->assertOk()
        ->assertSee("id=\"$table\"", false)
        ->assertSee("id=\"$dialog\"", false)
        ->assertDontSee('cdn.datatables.net', false)
        ->assertDontSee('.dataTables_wrapper', false);
})->with('fleet pages');

it('pages on the server', function () {
    $owner = Actors::companyOwner();
    VehicleFactory::new()->forCompany(Actors::companyOf($owner))->count(7)->create();

    $json = $this->actingAs($owner)->withHeaders(FleetAssets::ajax())
        ->get(route('admin.vehicle.index', ['draw' => 1, 'start' => 5, 'length' => 5, 'status' => 'active']))
        ->assertOk()->json();

    expect($json['recordsTotal'])->toBe(7)
        ->and($json['data'])->toHaveCount(2);
});

it('searches with the table search box', function () {
    $owner = Actors::companyOwner();
    $match = FleetAssets::trailerOf($owner, ['unit_no' => 'QZ-77']);
    FleetAssets::trailerOf($owner, ['unit_no' => 'OTHER-1']);

    $rows = $this->actingAs($owner)->withHeaders(FleetAssets::ajax())
        ->get(route('admin.trailer.index', ['draw' => 1, 'status' => 'active', 'search' => ['value' => 'qz-7']]))
        ->assertOk()->json('data');

    expect(array_column($rows, 'id'))->toBe([$match->id]);
});

it('hides the form and row actions from staff who can only view', function (string $route, string $permission, string $dialog, Closure $make) {
    $company = Actors::company();
    $viewer = Actors::memberWithPermissions($company, [$permission]);
    $make($company);

    $this->actingAs($viewer)->get(route($route))
        ->assertOk()->assertDontSee("id=\"$dialog\"", false)->assertDontSee('data-action="create"', false);

    $action = $this->withHeaders(FleetAssets::ajax())->get(route($route, ['draw' => 1]))->assertOk()->json('data.0.action');

    expect($action)->not->toContain('data-action="edit"')->not->toContain('data-action="delete"');
})->with([
    'vehicles' => ['admin.vehicle.index', 'vehicles.view', 'vehicle-dialog', fn ($company) => VehicleFactory::new()->forCompany($company)->create()],
    'trailers' => ['admin.trailer.index', 'trailers.view', 'trailer-dialog', fn ($company) => TrailerFactory::new()->forCompany($company)->create()],
    'asset groups' => ['admin.asset-group.index', 'asset-groups.view', 'asset-group-dialog', fn ($company) => AssetGroupFactory::new()->forCompany($company)->create()],
]);
