<?php

use Database\Factories\DriverFactory;
use Database\Factories\LicenseFactory;
use Illuminate\Support\Js;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;

/*
| UI-04 (phase 2, batch 1): dashboard and driver detail on the shared components.
*/

describe('dashboard', function () {
    it('shows only the sections the plan and role allow', function () {
        $owner = Actors::companyOwner();
        Actors::withPlan(Actors::companyOf($owner), ['drivers']);

        $this->actingAs($owner->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Driver compliance')
            ->assertDontSee('Fleet compliance')
            ->assertDontSee('Service log');
    });

    it('hides the driver widgets from a member without drivers.view', function () {
        $this->actingAs(Actors::plainUser())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Active drivers')
            ->assertSee('Driver application');
    });

    it('offers a first step when the company has no drivers', function () {
        $this->actingAs(Actors::companyOwner())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No drivers yet');
    });

    it('passes the application link to the copy button as a JS value', function () {
        $owner = Actors::companyOwner();
        $url = route('application.form', Actors::companyOf($owner)->slug);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(e(Js::from($url)), false);
    });
});

describe('driver detail', function () {
    it('renders accessible tabs', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();

        $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('aria-controls="driver-tabs-panel-files"', false);
    });

    it('shows the real license instead of placeholder data', function () {
        $owner = Actors::companyOwner();
        $driver = DriverFactory::new()->forCompany(DriverAdmin::companyOf($owner))->status('active')->create();
        LicenseFactory::new()->current()->create(['driver_id' => $driver->id, 'state_code' => 'TX', 'license_number' => 'TX0042', 'class' => 'A']);

        $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))
            ->assertOk()
            ->assertSee('TX · TX0042')
            // Icons carry an intrinsic 24px size, so a missing size-* class can't blow them up.
            ->assertSee('<svg width="24" height="24" aria-hidden="true" class="size-6 text-muted-foreground"', false)
            ->assertDontSee('Florida')
            ->assertDontSee('COMMERCIAL VALID')
            ->assertDontSee('07/23/2023');
    });

    it('offers hire and do-not-hire for a pending applicant to staff who may hire', function () {
        $owner = Actors::companyOwner();
        $driver = DriverFactory::new()->forCompany(DriverAdmin::companyOf($owner))->status('pending')->create();

        $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))
            ->assertOk()
            ->assertSee('id="hire-dialog"', false)
            ->assertSee('id="not-hire-dialog"', false);
    });

    it('hides the hire dialogs without drivers.hire', function () {
        $company = Actors::company();
        $viewer = Actors::memberWithPermissions($company, ['drivers.view']);
        $driver = DriverFactory::new()->forCompany($company)->status('pending')->create();

        $this->actingAs($viewer)->get(route('admin.driver.show', $driver->id))
            ->assertOk()
            ->assertDontSee('id="hire-dialog"', false)
            ->assertDontSee('Hire applicant');
    });

    it('does not print a stray form before the document', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();

        $html = $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))->assertOk()->getContent();

        expect(ltrim($html))->toStartWith('<!doctype html>')
            ->and($html)->not->toContain('deleteDriverForm');
    });
});
