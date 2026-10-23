<?php

use App\Features\Feature;
use App\Jobs\BuildExport;
use App\Models\AuditLog;
use App\Models\Export;
use App\Support\Navigation\CompanyMenu;
use Database\Factories\DriverFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Exports\Exports;
use Tests\Support\Actors;

/*
| Requesting a ZIP export (req 2.5, 10 §9): exports.create, the zip_export feature, the
| driver's own visibility, reuse of a pending export of the same scope, read-only (Q2), audit.
*/

beforeEach(function () {
    Storage::fake('local');
});

it('queues a driver export on the exports connection and audits the request', function () {
    Queue::fake();
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->create();

    $this->actingAs($company->owner)->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id])
        ->assertRedirect(route('exports.index'));

    $export = Exports::latest($company);
    expect($export->only(['type', 'status', 'parameters', 'requested_by_user_id']))->toEqual([
        'type' => 'zip_driver', 'status' => 'queued', 'parameters' => ['driver_id' => $driver->id], 'requested_by_user_id' => $company->owner_user_id,
    ]);
    Queue::assertPushedOn('exports', BuildExport::class, fn (BuildExport $job) => $job->exportId === $export->id
        && $job->companyId === $company->id && $job->connection === 'exports');
    expect(AuditLog::query()->where('company_id', $company->id)->where('action', 'export.requested')->where('subject_id', $export->id)->exists())->toBeTrue();
});

it('returns the pending export of the same scope instead of queuing another', function () {
    Queue::fake();
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    $this->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id]);
    $this->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id]);
    Export::withoutTenancy()->where('company_id', $company->id)->update(['status' => 'running']);
    $this->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id]);

    expect(Export::withoutTenancy()->where('company_id', $company->id)->count())->toBe(1);
    Queue::assertPushed(BuildExport::class, 1);

    $this->post(route('exports.store'), ['type' => 'zip_company']);
    expect(Export::withoutTenancy()->where('company_id', $company->id)->count())->toBe(2);
});

it('queues a new export once the earlier one is ready', function () {
    Queue::fake();
    $company = Actors::company();
    $this->actingAs($company->owner);

    $this->post(route('exports.store'), ['type' => 'zip_company']);
    Export::withoutTenancy()->where('company_id', $company->id)->update(['status' => 'ready']);
    $this->post(route('exports.store'), ['type' => 'zip_company']);

    Queue::assertPushed(BuildExport::class, 2);
});

it('needs exports.create', function () {
    Queue::fake();
    $company = Actors::company();
    $user = Actors::memberWithPermissions($company, ['exports.download', 'drivers.view']);

    $this->actingAs($user)->post(route('exports.store'), ['type' => 'zip_company'])->assertForbidden();
    $this->get(route('exports.index'))->assertOk();
    Queue::assertNothingPushed();
});

it('needs drivers.view and folders.view for what the ZIP holds', function (array $permissions, int $status) {
    Queue::fake();
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->create();
    $user = Actors::memberWithPermissions($company, ['exports.create', ...$permissions]);

    expect($this->actingAs($user)->post(route('exports.store'), ['type' => 'zip_company'])->status())->toBe($status)
        ->and($this->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id])->status())->toBe($status);
})->with([
    'exports.create only' => [[], 403],
    'without folders.view' => [['drivers.view'], 403],
    'without drivers.view' => [['folders.view'], 403],
    'both' => [['drivers.view', 'folders.view'], 302],
]);

it('does not need folders.view when the plan has no folders', function () {
    Queue::fake();
    $company = Actors::company();
    Actors::withPlan($company, collect(Feature::cases())->reject(fn (Feature $f) => $f === Feature::DocumentFolders || $f->isLimit())->map->value->all());
    $user = Actors::memberWithPermissions($company, ['exports.create', 'drivers.view']);

    $this->actingAs($user)->post(route('exports.store'), ['type' => 'zip_company'])->assertRedirect(route('exports.index'));
});

it('hides the ZIP buttons from a user who could not open what the ZIP holds', function () {
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->create();
    $user = Actors::memberWithPermissions($company, ['exports.create', 'drivers.view', 'folders.view']);
    $partial = Actors::memberWithPermissions($company, ['exports.create', 'drivers.view']);

    $this->actingAs($user)->get(route('documents.index'))->assertSee('Download company ZIP');
    $this->get(route('admin.driver.show', $driver->id))->assertSee('Download ZIP');
    $this->actingAs($partial)->get(route('admin.driver.show', $driver->id))->assertOk()->assertDontSee('Download ZIP');
});

it('refuses another company\'s driver and invalid input', function () {
    Queue::fake();
    $company = Actors::company();
    $other = DriverFactory::new()->forCompany(Actors::company())->create();
    $this->actingAs($company->owner);

    $this->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $other->id])->assertSessionHasErrors('driver_id');
    $this->post(route('exports.store'), ['type' => 'zip_driver'])->assertSessionHasErrors('driver_id');
    $this->post(route('exports.store'), ['type' => 'year_end_report'])->assertSessionHasErrors('type');
    $this->post(route('exports.store'), ['type' => 'zip_company', 'driver_id' => $other->id])->assertSessionHasErrors('driver_id');
    expect(Export::withoutTenancy()->count())->toBe(0);
});

it('hides the menu, the buttons and the routes without the zip_export feature', function () {
    $company = Actors::company();
    Actors::withPlan($company, collect(Feature::cases())->reject(fn (Feature $f) => $f === Feature::ZipExport || $f->isLimit())->map->value->all());
    $driver = DriverFactory::new()->forCompany($company)->create();
    $owner = $company->owner;

    $this->actingAs($owner)->post(route('exports.store'), ['type' => 'zip_company'])->assertForbidden();
    $this->get(route('exports.index'))->assertForbidden();
    $this->get(route('admin.driver.show', $driver->id))->assertOk()->assertDontSee(route('exports.store'));
    $this->get(route('documents.index'))->assertOk()->assertDontSee('Download company ZIP');
});

it('shows the Exports menu item and the ZIP buttons', function () {
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    $this->get(route('admin.driver.show', $driver->id))->assertOk()->assertSee(route('exports.store'))->assertSee('Download ZIP');
    $this->get(route('documents.index'))->assertOk()->assertSee('Download company ZIP');
    $this->get(route('exports.index'))->assertOk()->assertSee('Exports');

    $owner = Actors::actingAsTenant($company->owner);
    $labels = collect(CompanyMenu::for($owner, new Request))->flatMap(fn ($group) => collect($group['items'])->pluck('label'));
    expect($labels)->toContain('Exports');
});

it('lets a read-only company export its DQ files (Q2)', function () {
    $company = Actors::readOnly(Actors::company());
    $driver = DriverFactory::new()->forCompany($company)->create();

    $this->actingAs($company->owner)->post(route('exports.store'), ['type' => 'zip_driver', 'driver_id' => $driver->id])
        ->assertRedirect(route('exports.index'));

    expect(Exports::latest($company)->status)->toBe('ready');
});
