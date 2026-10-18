<?php

use App\Models\Accident;
use App\Models\Country;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\EmploymentRecord;
use App\Models\ResidenceAddress;
use App\Models\State;
use App\Models\User;
use App\Models\Violation;
use Database\Factories\DriverFactory;
use Database\Factories\LicenseFactory;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;

/*
| UI-05 (phase 2, batch 2): one shared driver create/edit form. The old edit page read columns
| that do not exist (country, state, accident, violation), rendered dates as datetimes and only
| the first residence, so saving it unchanged dropped data. Employment radios shared one group.
*/

beforeEach(function () {
    $us = Country::forceCreate(['name' => 'United States', 'iso_code' => 'US']);
    Country::forceCreate(['name' => 'Canada', 'iso_code' => 'CA']);
    State::forceCreate(['country_id' => $us->id, 'name' => 'Texas', 'code' => 'TX']);
    State::forceCreate(['country_id' => $us->id, 'name' => 'Ohio', 'code' => 'OH']);
});

/**
 * A driver with two residences, an accident, a violation and two employers.
 *
 * @return array{0: User, 1: Driver}
 */
function driverWithHistory(): array
{
    $owner = Actors::companyOwner();
    $driver = DriverFactory::new()->forCompany(DriverAdmin::companyOf($owner))->status('pending')->create([
        'date_of_birth' => '1990-04-05', 'state_code' => 'OH', 'country_code' => 'US',
    ]);
    LicenseFactory::new()->current()->create(['driver_id' => $driver->id, 'state_code' => 'TX', 'country_code' => 'US', 'license_number' => 'TX0042']);
    ResidenceAddress::forceCreate(['driver_id' => $driver->id, 'address' => '1 First St', 'city' => 'Austin', 'state_code' => 'TX', 'country_code' => 'US', 'zip' => '73301', 'is_current' => true]);
    ResidenceAddress::forceCreate(['driver_id' => $driver->id, 'address' => '2 Second St', 'city' => 'Dayton', 'state_code' => 'OH', 'country_code' => 'US', 'zip' => '45402', 'is_current' => false]);
    Accident::forceCreate(['driver_id' => $driver->id, 'accident_date' => '2025-03-01', 'accident_location' => 'Waco', 'number_of_injuries' => 1, 'number_of_fatalities' => 0, 'hazmat_spill' => false]);
    Violation::forceCreate(['driver_id' => $driver->id, 'violation_date' => '2025-06-01', 'violation_location' => 'Tyler', 'offense' => 'Speeding', 'vehicle_type' => 'Truck']);
    DriverDocument::updateOrCreate(['driver_id' => $driver->id], ['has_accidents' => true, 'has_violations' => true]);
    EmploymentRecord::forceCreate(['driver_id' => $driver->id, 'employer_name' => 'Acme', 'employed_regulations' => true, 'safety_sensitive_function' => false]);
    EmploymentRecord::forceCreate(['driver_id' => $driver->id, 'employer_name' => 'Globex', 'employed_regulations' => false, 'safety_sensitive_function' => true]);

    return [$owner, $driver];
}

/**
 * What the browser posts for the form as rendered: named controls outside <template>.
 *
 * @return array<string, mixed>
 */
function postedFields(string $html): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $pairs = [];

    foreach ($xpath->query('//form[@id="driverForm"]//*[(self::input or self::select or self::textarea) and @name][not(ancestor::template)]') as $el) {
        $name = $el->getAttribute('name');
        $type = strtolower($el->getAttribute('type'));

        if ($el->nodeName === 'select') {
            $selected = $xpath->query('.//option[@selected]', $el)->item(0) ?? $xpath->query('.//option', $el)->item(0);
            $value = $selected?->getAttribute('value') ?? '';
        } elseif (in_array($type, ['checkbox', 'radio'], true)) {
            if (! $el->hasAttribute('checked')) {
                continue;
            }
            $value = $el->getAttribute('value');
        } elseif ($type === 'file') {
            continue;
        } else {
            $value = $el->nodeName === 'textarea' ? $el->textContent : $el->getAttribute('value');
        }

        $pairs[] = urlencode($name).'='.urlencode($value);
    }

    parse_str(implode('&', $pairs), $fields);

    return $fields;
}

describe('create', function () {
    it('renders every section with US preselected and indexed row names', function () {
        $this->actingAs(Actors::companyOwner())->get(route('admin.driver.create'))
            ->assertOk()
            ->assertSee('id="section-employment"', false)
            ->assertSee('<option value="US" selected>', false)
            ->assertSee('name="residence_address[0]"', false)
            ->assertSee('name="employed_regulations[0]"', false)
            ->assertSee('name="employed_regulations[__INDEX__]"', false);
    });

    it('stores each employer with its own regulation answers', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)->post(route('admin.driver.store'), DriverAdmin::payload([
            'employer_name' => [0 => 'Acme', 2 => 'Globex'],
            'employed_regulations' => [0 => 'no', 2 => 'yes'],
            'safety_sensitive_function' => [0 => 'yes', 2 => 'no'],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $records = EmploymentRecord::orderBy('id')->get();
        expect($records->pluck('employer_name')->all())->toBe(['Acme', 'Globex'])
            ->and($records->pluck('employed_regulations')->all())->toBe([false, true])
            ->and($records->pluck('safety_sensitive_function')->all())->toBe([true, false]);
    });

    it('rejects text in the miles column instead of failing on insert', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)->from(route('admin.driver.create'))->post(route('admin.driver.store'), DriverAdmin::payload([
            'equipment_class' => ['Straight Truck'], 'experience' => ['yes'], 'approx_miles' => ['lots'],
        ]))->assertSessionHasErrors(['approx_miles.0' => 'The approximate miles field must be an integer.'])
            ->assertSessionDoesntHaveErrors('system_error');

        $this->get(route('admin.driver.create'))
            ->assertSee('href="#f-approx_miles-0"', false)
            ->assertDontSee('could not be saved');
    });

    it('re-renders posted rows under their own index after a failed submit', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)->from(route('admin.driver.create'))->post(route('admin.driver.store'), DriverAdmin::payload([
            'first_name' => '',
            'residence_address' => [0 => '1 First St', 3 => '9 Ninth St'],
        ]))->assertRedirect(route('admin.driver.create'));

        $this->get(route('admin.driver.create'))
            ->assertSee('name="residence_address[3]"', false)
            ->assertSee('value="9 Ninth St"', false)
            ->assertSee('data-next-index="4"', false)
            ->assertSee('href="#f-first_name"', false);
    });
});

describe('edit', function () {
    it('shows the saved values in the form', function () {
        [$owner, $driver] = driverWithHistory();

        $fields = postedFields($this->actingAs($owner)->get(route('admin.driver.edit', $driver->id))->assertOk()->getContent());

        expect($fields['date_of_birth'])->toBe('1990-04-05')
            ->and($fields['state'])->toBe('OH')
            ->and($fields['country'])->toBe('US')
            ->and($fields['license_state'])->toBe('TX')
            ->and($fields['license_number'])->toBe('TX0042')
            ->and($fields['residence_address'])->toBe(['1 First St', '2 Second St'])
            ->and($fields['accident'])->toBe('yes')
            ->and($fields['accident_date'])->toBe(['2025-03-01'])
            ->and($fields['violation'])->toBe('yes')
            ->and($fields['offense'])->toBe(['Speeding'])
            ->and($fields['employed_regulations'])->toBe(['yes', 'no'])
            ->and($fields['safety_sensitive_function'])->toBe(['no', 'yes']);
    });

    it('keeps the history when the form is saved unchanged', function () {
        [$owner, $driver] = driverWithHistory();
        $fields = postedFields($this->actingAs($owner)->get(route('admin.driver.edit', $driver->id))->getContent());

        $this->put(route('admin.driver.update', $driver->id), $fields + ['_token' => csrf_token()])
            ->assertSessionHasNoErrors()->assertRedirect();

        $driver->refresh();
        expect($driver->residence_addresses()->count())->toBe(2)
            ->and($driver->accidents()->count())->toBe(1)
            ->and($driver->violations()->count())->toBe(1)
            ->and($driver->employment_records()->orderBy('id')->pluck('employed_regulations')->all())->toBe([true, false])
            ->and($driver->state_code)->toBe('OH')
            ->and($driver->date_of_birth->toDateString())->toBe('1990-04-05');
    });

    it('is still forbidden without drivers.edit', function () {
        $company = Actors::company();
        $viewer = Actors::memberWithPermissions($company, ['drivers.view']);
        $driver = DriverFactory::new()->forCompany($company)->create();

        $this->actingAs($viewer)->get(route('admin.driver.edit', $driver->id))->assertForbidden();
    });
});
