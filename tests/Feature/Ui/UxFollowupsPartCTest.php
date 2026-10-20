<?php

use App\Models\Country;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Support\Driver\DriverForm;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| UX follow-ups part C: public application step 1 resumes from the saved application
| (DriverForm, like the admin edit form). Repeating rows post indexed names x[N], so each
| employment row has its own employed_regulations / safety_sensitive_function radio group.
*/

beforeEach(function () {
    Applicant::fakeOtp();
    $this->company = Applicant::company();
    $this->driver = Applicant::draft($this->company);
    DriverDocument::create(['driver_id' => $this->driver->id]);

    Country::create(['name' => 'Canada', 'iso_code' => 'CA']);
    $this->us = Country::create(['name' => 'United States', 'iso_code' => 'US']);
    $this->texas = $this->us->states()->create(['name' => 'Texas', 'code' => 'TX']);
    $this->california = $this->us->states()->create(['name' => 'California', 'code' => 'CA']);
});

/**
 * Step 1 as the browser posts it: countries and states as ids, rows as x[N].
 *
 * @return array<string, mixed>
 */
function partCPayload(Driver $driver, array $overrides = []): array
{
    $us = test()->us->id;
    $tx = test()->texas->id;

    return Applicant::step1Payload($driver, array_merge([
        'country' => $us, 'state' => $tx, 'license_country' => $us, 'license_state' => $tx,
        'license_class' => 'Class A (CDL-A)', 'federal_tax_classification' => 'llc', 'twic_card' => '1', 'is_t_double_trailer' => '1',
        'equipment_class' => DriverForm::EQUIPMENT_CLASSES, 'experience' => ['yes', 'no', 'no', 'no', 'no', 'no', 'no'],
        'experience_from_date' => ['2018-01-01'], 'experience_to_date' => ['2020-01-01'], 'approx_miles' => ['50000'],
        'residence_address' => ['1 Main St', '9 Oak Ave'], 'residence_city' => ['Austin', 'Fresno'],
        'residence_country' => [$us, $us], 'residence_state' => [$tx, test()->california->id], 'residence_postal_code' => ['73301', '93650'],
        'accident' => 'yes', 'accident_date' => ['2024-03-01', '2023-05-02'], 'accident_location' => ['Austin, TX', 'Waco, TX'],
        'number_of_injuries' => ['1', '0'], 'number_of_fatalities' => ['0', '0'], 'hazmat_spill' => ['no', 'yes'],
        'violation' => 'yes', 'violation_date' => ['2024-06-01'], 'violation_location' => ['Dallas, TX'], 'offense' => ['Speeding'], 'vehicle_type' => ['Truck'],
        'employer_name' => ['Acme Freight', 'Beta Logistics'], 'employer_record_city' => ['Austin', 'Houston'],
        'employer_record_country' => [$us, $us], 'employer_record_state' => [$tx, $tx], 'employer_record_email' => ['hr@acme.test', 'hr@beta.test'],
        'employer_record_date_from' => ['2022-01-01', '2019-01-01'], 'employer_record_date_to' => ['2024-01-01', '2021-12-31'],
        'employed_regulations' => ['yes', 'no'], 'safety_sensitive_function' => ['no', 'yes'],
    ], $overrides));
}

function partCSave(array $payload)
{
    return test()->withSession(Applicant::session(test()->driver))
        ->from(route('public.application.step1', test()->company->slug))
        ->post(route('public.application.store.step1', test()->company->slug), $payload);
}

function partCPage(): string
{
    return test()->withSession(Applicant::session(test()->driver))
        ->get(route('public.application.step1', test()->company->slug))->assertOk()->getContent();
}

/** The checked value of a radio group, or null. */
function partCChecked(string $html, string $name): ?string
{
    return preg_match('~name="'.preg_quote($name, '~').'" value="(\w+)"\s+checked~', $html, $m) ? $m[1] : null;
}

/** The selected option value of a select, or null. */
function partCSelected(string $html, string $name): ?string
{
    $start = strpos($html, 'name="'.$name.'"');
    $select = substr($html, $start, strpos($html, '</select>', $start) - $start);

    return preg_match('~<option value="([^"]*)"\s+selected~', $select, $m) ? $m[1] : null;
}

it('shows the saved values and rows when an applicant resumes step 1', function () {
    partCSave(partCPayload($this->driver))->assertSessionHasNoErrors();

    $html = partCPage();

    expect($html)
        ->toContain('value="Jane"')
        ->toContain('value="D1234567"')
        ->toMatch('~id="repeat_license_number"[^>]*value="D1234567"~')
        ->toMatch('~name="twic_card" value="1" checked~')
        ->toMatch('~name="is_t_double_trailer" value="1" checked~')
        ->not->toMatch('~name="passport" value="1" checked~')
        ->toMatch('~name="experience_from_date\[\]" value="2018-01-01"~')
        ->toMatch('~id="residence_address_1" name="residence_address\[1\]"\s+value="9 Oak Ave"~')
        ->toMatch('~name="accident_date\[1\]" value="2023-05-02"~')
        ->toMatch('~name="violation_date\[0\]" value="2024-06-01"~')
        ->toMatch('~id="employer_name_1" name="employer_name\[1\]"\s+value="Beta Logistics"~')
        ->toContain('Employment Record 2')
        ->toContain('let residenceCount = 2;')
        ->toContain('let accidentCount = 2;')
        ->toContain('let violationCount = 1;')
        ->toContain('let employmentRecordCount = 2;');

    expect(partCChecked($html, 'federal_tax_classification'))->toBe('llc')
        ->and(partCChecked($html, 'accident'))->toBe('yes')
        ->and(partCChecked($html, 'violation'))->toBe('yes')
        ->and(partCSelected($html, 'country'))->toBe((string) $this->us->id)
        ->and(partCSelected($html, 'license_state'))->toBe((string) $this->texas->id)
        ->and(partCSelected($html, 'license_class'))->toBe('Class A (CDL-A)')
        ->and(partCSelected($html, 'residence_state[1]'))->toBe((string) $this->california->id)
        ->and(partCSelected($html, 'hazmat_spill[1]'))->toBe('yes');
});

it('gives each employment row its own radio groups and saves each answer as given', function () {
    partCSave(partCPayload($this->driver))->assertSessionHasNoErrors();

    $records = $this->driver->employment_records()->orderBy('employer_name')->get();
    expect($records->pluck('employer_name')->all())->toBe(['Acme Freight', 'Beta Logistics'])
        ->and($records->map(fn ($r) => [(bool) $r->employed_regulations, (bool) $r->safety_sensitive_function])->all())
        ->toBe([[true, false], [false, true]]);

    // Resumed newest first (date_to desc, as admin edit): Acme is row 0, Beta row 1
    $html = partCPage();
    expect(partCChecked($html, 'employed_regulations[0]'))->toBe('yes')
        ->and(partCChecked($html, 'safety_sensitive_function[0]'))->toBe('no')
        ->and(partCChecked($html, 'employed_regulations[1]'))->toBe('no')
        ->and(partCChecked($html, 'safety_sensitive_function[1]'))->toBe('yes')
        ->and($html)->not->toContain('name="employed_regulations[]"')
        ->and($html)->toContain('name="employed_regulations[${employmentRecordCount}]" value="no" checked');
});

it('keeps the posted rows and puts each error on its own row after a failed submit', function () {
    partCSave(partCPayload($this->driver, [
        'first_name' => '',
        'employer_record_email' => ['hr@acme.test', 'not-an-email'],
        'employed_regulations' => ['no', 'yes'],
    ]))->assertSessionHasErrors(['first_name', 'employer_record_email.1']);

    $html = partCPage();

    expect($html)
        ->toMatch('~id="first_name"[^>]*aria-invalid="true"~')
        ->toMatch('~id="employer_record_email_1" name="employer_record_email\[1\]"\s+value="not-an-email"\s+aria-invalid="true"~')
        ->toMatch('~<p role="alert"\s+id="employer_record_email_1-error"\s+class="text-sm text-destructive">~')
        ->not->toContain('id="employer_record_email_0-error"')
        ->toMatch('~id="employer_record_email_0" name="employer_record_email\[0\]"\s+value="hr@acme.test"\s+class~')
        ->toMatch('~id="residence_address_1" name="residence_address\[1\]"\s+value="9 Oak Ave"~')
        ->toContain('let employmentRecordCount = 2;');

    // Posted ids select the same options again
    expect(partCChecked($html, 'employed_regulations[0]'))->toBe('no')
        ->and(partCChecked($html, 'employed_regulations[1]'))->toBe('yes')
        ->and(partCSelected($html, 'country'))->toBe((string) $this->us->id)
        ->and(partCSelected($html, 'residence_state[1]'))->toBe((string) $this->california->id);
});

it('preselects the United States and the No answers on a new application', function () {
    $html = partCPage();

    foreach (['country', 'license_country', 'residence_country[0]', 'employer_record_country[0]'] as $select) {
        expect(partCSelected($html, $select))->toBe((string) $this->us->id);
    }
    expect(partCSelected($html, 'hazmat_spill[0]'))->toBe('no')
        ->and(partCChecked($html, 'employed_regulations[0]'))->toBe('no')
        ->and($html)->toContain('let residenceCount = 1;');
});

it('still shows the saved rows on the admin edit form', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();
    $driver->employment_records()->create(['employer_name' => 'Gamma Haul', 'employed_regulations' => true, 'safety_sensitive_function' => false]);

    $html = $this->actingAs($owner)->get(route('admin.driver.edit', $driver))->assertOk()->getContent();

    expect($html)->toMatch('~name="employer_name\[0\]"[^>]*value="Gamma Haul"~')
        ->and(partCChecked($html, 'employed_regulations[0]'))->toBeNull()
        ->and($html)->toMatch('~name="employed_regulations\[0\]" id="[^"]+" value="yes" class="input"\s+checked~');
});
