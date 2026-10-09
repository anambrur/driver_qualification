<?php

use App\Models\Country;
use Tests\Feature\Modules\Drivers\DriverAdmin;

/*
| DRV-09: the wizard pages pass the {driver_id} route string to loadWizardDriver(int), so a
| non-numeric id was a TypeError (500) instead of a 404. edit() was also suspected to crash
| when there is no `US` country row.
*/

dataset('wizard pages', [
    'admin.driver.license', 'admin.driver.medical.card', 'admin.driver.forfeiture', 'admin.driver.violation',
    'admin.driver.alcohol.and.drug.test', 'admin.driver.fmcsa.consent', 'admin.driver.psp',
    'admin.driver.alcohol.and.drug.test.policy', 'admin.driver.general.work.policy',
]);

describe('DRV-09: bad route ids', function () {
    it('returns 404 for a non-numeric driver id on every wizard page', function (string $route) {
        [$owner] = DriverAdmin::tenantWithDriver();

        $this->actingAs($owner)->get(route($route, 'abc'))->assertNotFound();
    })->with('wizard pages');

    it('returns 404 for a non-numeric id on the driver pages', function () {
        [$owner] = DriverAdmin::tenantWithDriver();
        $this->actingAs($owner);

        $this->get(route('admin.driver.show', 'abc'))->assertNotFound();
        $this->get(route('admin.driver.edit', 'abc'))->assertNotFound();
        $this->getJson(route('admin.drivers.get-driver-details', 'abc'))->assertNotFound();
    });

    it('still renders every wizard page for the tenant\'s own driver', function (string $route) {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();

        $this->actingAs($owner)->get(route($route, $driver->id))->assertOk();
    })->with('wizard pages');

    it('renders the edit page when there is no US country row', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        Country::query()->where('iso_code', 'US')->delete();

        $this->actingAs($owner)->get(route('admin.driver.edit', $driver->id))->assertOk();
    });
});
