<?php

use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;

/*
| UI-05: input that passes validation must fit the columns. These used to pass validation and
| then fail the insert (NOT NULL residence columns, char(2) codes, integer and varchar(255)
| columns), which showed only the generic "An error occurred" message.
*/

it('reports a field error instead of failing the insert on create', function (array $overrides, array $fields) {
    $this->actingAs(Actors::companyOwner())->from(route('admin.driver.create'))
        ->post(route('admin.driver.store'), DriverAdmin::payload($overrides))
        ->assertRedirect(route('admin.driver.create'))
        ->assertSessionHasErrors($fields)
        ->assertSessionDoesntHaveErrors('system_error');
})->with([
    'residence with only an address' => [['residence_address' => ['1 Main St']], ['residence_city.0', 'residence_country.0', 'residence_state.0', 'residence_postal_code.0']],
    'residence without a state' => [['residence_address' => ['1 Main St'], 'residence_city' => ['Austin'], 'residence_country' => ['US'], 'residence_state' => [null], 'residence_postal_code' => ['73301']], ['residence_state.0']],
    'unknown license state' => [['license_state' => 'Atlantis'], ['license_state']],
    'unknown state id' => [['state' => '999999'], ['state']],
    'text in miles' => [['experience' => ['yes'], 'approx_miles' => ['lots']], ['approx_miles.0']],
    'too many injuries' => [['accident' => 'yes', 'accident_date' => ['2024-01-01'], 'number_of_injuries' => ['70000']], ['number_of_injuries.0']],
    'offense longer than the column' => [['violation' => 'yes', 'violation_date' => ['2024-01-01'], 'offense' => [str_repeat('x', 256)]], ['offense.0']],
    'experience ends before it starts' => [['experience' => ['yes'], 'experience_from_date' => ['2020-01-01'], 'experience_to_date' => ['2019-01-01']], ['experience_to_date.0' => 'The experience end date must be on or after the experience start date.']],
    'employment ends before it starts' => [['employer_name' => ['Acme'], 'employer_record_date_from' => ['2020-01-01'], 'employer_record_date_to' => ['2019-12-31']], ['employer_record_date_to.0']],
    'unknown employer state' => [['employer_name' => ['Acme'], 'employer_record_state' => ['ZZZ']], ['employer_record_state.0']],
]);

it('accepts an end date on the start date or without a start date', function () {
    $this->actingAs(Actors::companyOwner())
        ->post(route('admin.driver.store'), DriverAdmin::payload([
            'experience' => ['yes'], 'experience_from_date' => ['2020-01-01'], 'experience_to_date' => ['2020-01-01'],
            'employer_name' => ['Acme'], 'employer_record_date_from' => [null], 'employer_record_date_to' => ['2019-12-31'],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('saves a driver whose license has already expired', function () {
    $this->actingAs(Actors::companyOwner())
        ->post(route('admin.driver.store'), DriverAdmin::payload(['license_issued' => '2010-01-01', 'license_expires' => '2015-01-01']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('asks for the rest of the license when the edit form posts only a number', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();

    $this->actingAs($owner)->from(route('admin.driver.edit', $driver->id))
        ->put(route('admin.driver.update', $driver->id), DriverAdmin::updatePayload($driver, [
            'license_first_name' => null, 'license_issued' => null, 'license_state' => null, 'license_class' => null,
        ]))
        ->assertSessionHasErrors(['license_first_name', 'license_issued', 'license_state', 'license_class'])
        ->assertSessionDoesntHaveErrors('system_error');
});

it('still saves the edit form without any license fields', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();
    $noLicense = array_fill_keys(['license_number', 'repeat_license_number', 'license_first_name', 'license_last_name', 'license_issued', 'license_expires', 'license_country', 'license_state', 'license_class'], null);

    $this->actingAs($owner)->put(route('admin.driver.update', $driver->id), DriverAdmin::updatePayload($driver, $noLicense))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});
