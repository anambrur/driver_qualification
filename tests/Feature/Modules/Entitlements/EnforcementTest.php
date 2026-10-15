<?php

use App\Features\Feature;
use App\Mail\DriverComplianceDigestMail;
use App\Services\Entitlements\PlanFeatureService;
use App\Services\Entitlements\RetainedFeatures;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Modules\Entitlements\Entitled;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Fixtures\MaintenanceTenantJob;
use Tests\Fixtures\RecordingTenantJob;
use Tests\Support\Actors;

/*
| Phase 0 acceptance (13): disabling maintenance in a plan blocks its routes, menus and jobs
| for every company on that plan within one request, through version invalidation.
| Entry points (05 §4): feature: middleware, Gate::before, @feature, TenantAware, scheduler.
*/

beforeEach(function () {
    RecordingTenantJob::$seen = [];
    [$this->owner, $this->vehicle] = Maintenance::tenantWithVehicle();
    $this->company = Actors::companyOf($this->owner);
});

it('blocks maintenance routes, menus and jobs on the next request after the plan disables it', function () {
    $otherOwner = Actors::companyOwner();
    $menuLink = 'href="'.route('admin.service-log.index').'"';

    $this->actingAs($this->owner)->get(route('users.index'))->assertOk()->assertSee($menuLink, false);
    $this->actingAs($this->owner)->get(route('admin.service-log.index'))->assertOk();
    MaintenanceTenantJob::dispatchSync($this->company->id);
    expect(RecordingTenantJob::$seen)->toHaveCount(1);

    app(PlanFeatureService::class)->set(Actors::platformOwner(), Entitled::planOf($this->company), Feature::Maintenance, false);
    Entitled::newRequest();
    $owner = $this->owner->fresh();

    $this->actingAs($owner)->get(route('admin.service-log.index'))->assertForbidden();
    $this->actingAs($owner)->getJson(route('admin.maintenance-schedule.index'))
        ->assertForbidden()
        ->assertJsonPath('feature', 'maintenance');
    $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($this->vehicle))->assertForbidden();
    $this->actingAs($owner)->get(route('users.index'))->assertOk()->assertDontSee($menuLink, false);
    MaintenanceTenantJob::dispatchSync($this->company->id);
    expect(RecordingTenantJob::$seen)->toHaveCount(1);

    // Another company on another plan keeps maintenance.
    $this->actingAs($otherOwner)->get(route('admin.service-log.index'))->assertOk();
    MaintenanceTenantJob::dispatchSync(Actors::companyOf($otherOwner)->id);
    expect(RecordingTenantJob::$seen)->toHaveCount(2);
});

it('keeps a module lost through a downgrade viewable, but not writable', function () {
    $current = Actors::openSubscription($this->company)->plan_price_id;
    $smaller = Entitled::plan(array_fill_keys(array_column(array_filter(
        Feature::cases(), fn (Feature $feature) => $feature !== Feature::Maintenance,
    ), 'value'), null));
    RetainedFeatures::priceChanged($this->company, $current, $smaller->id);
    Entitled::subscribe($this->company, $smaller);
    Entitled::newRequest();
    $owner = $this->owner->fresh();

    $this->actingAs($owner)->get(route('admin.service-log.index'))->assertOk();
    $this->actingAs($owner)->get(route('users.index'))->assertSee('href="'.route('admin.service-log.index').'"', false);
    $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($this->vehicle))->assertForbidden();

    MaintenanceTenantJob::dispatchSync($this->company->id);
    MaintenanceTenantJob::dispatchSync($this->company->id, readOnly: true);
    expect(RecordingTenantJob::$seen)->toHaveCount(1);
});

it('lets a lapsed company view and export but not write', function () {
    $owner = Actors::actingAsTenant(Actors::readOnly($this->company)->owner);

    expect($owner->can('maintenance.view'))->toBeTrue()
        ->and($owner->can('exports.create'))->toBeTrue()
        ->and($owner->can('maintenance.create'))->toBeFalse()
        ->and($owner->can('drivers.create'))->toBeFalse()
        ->and($owner->can('billing.manage'))->toBeTrue();
});

it('denies a permission whose feature the plan does not include, whatever the role holds', function () {
    Entitled::subscribe($this->company, Entitled::plan(['drivers' => null]));
    Entitled::newRequest();
    $owner = Actors::actingAsTenant($this->owner->fresh());

    expect($owner->can('drivers.create'))->toBeTrue()
        ->and($owner->can('maintenance.view'))->toBeFalse()
        ->and($owner->can('roles.create'))->toBeFalse()
        ->and($owner->can('users.view'))->toBeTrue();
});

it('shows @feature content only for an entitled feature', function () {
    Entitled::subscribe($this->company, Entitled::plan(['drivers' => null]));
    Entitled::newRequest();
    $this->actingAs($this->owner->fresh());

    $html = Blade::render("@feature('drivers') [drivers] @endfeature @feature('maintenance') [maintenance] @endfeature");

    expect($html)->toContain('[drivers]')->not->toContain('[maintenance]');
});

it('sends the compliance digest only to companies with compliance reminders on', function () {
    Mail::fake();
    DocumentTypeFactory::new()->module('driver')->create(['status' => true]);
    $entitled = DriverFactory::new()->forCompany($this->company)->create();
    $other = Entitled::subscribe(Actors::company(), Entitled::plan(['drivers' => null]));
    DriverFactory::new()->forCompany($other)->create();

    $this->artisan('compliance:send-digest-reminders')->assertSuccessful();

    Mail::assertQueued(DriverComplianceDigestMail::class, 1);
    Mail::assertQueued(DriverComplianceDigestMail::class, fn ($mail) => $mail->hasTo($entitled->email));
});
