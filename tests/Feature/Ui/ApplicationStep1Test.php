<?php

use App\Models\DriverDocument;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| UI-16 (phase 2, batch 8b): public application step 1 on x-ui cards and fields. The page keeps
| its own markup instead of the admin driver form partials (those post indexed names such as
| residence_address[0]; step 1 posts residence_address[] and driver-form.js is not loaded here),
| so the field order, names and ids below are the contract with StoreApplicationStep1Request and
| the step's own scripts.
*/

beforeEach(function () {
    Applicant::fakeOtp();
    $this->company = Applicant::company();
    $this->driver = Applicant::draft($this->company, ['first_name' => 'Jane', 'last_name' => 'Roe']);
    DriverDocument::create(['driver_id' => $this->driver->id]);
});

function step1Page(array $session = [])
{
    return test()->withSession(Applicant::session(test()->driver) + $session)
        ->get(route('public.application.step1', test()->company->slug));
}

/**
 * The form's fields in order, as "name" or "name#id".
 *
 * @return list<string>
 */
function step1Fields(string $html): array
{
    $start = strpos($html, 'id="driverForm"');
    $form = substr($html, $start, strpos($html, '</form>', $start) - $start);
    preg_match_all('~<(?:input|select|textarea)\b[^>]*>~', $form, $tags);

    return collect($tags[0])
        ->map(fn (string $tag) => preg_match('~\bname="([^"]+)"~', $tag, $name) && $name[1] !== '_token'
            ? $name[1].(preg_match('~\bid="([^"]+)"~', $tag, $id) ? '#'.$id[1] : '')
            : null)
        ->filter()
        ->values()
        ->all();
}

it('renders step 1 on x-ui cards and fields', function () {
    $html = step1Page()->assertOk()
        ->assertSee(['Applicant Information', 'Residences Previous 3 Years', 'Drivers License Information', 'Experience',
            'Accidents/Crashes Previous 3 Years', 'Moving Traffic Violations Previous 3 Years', 'Forfeitures Previous 3 Years',
            'Employment Record Previous 3 Years'])
        ->assertSee(['<section class="card scroll-mt-24" id="section-applicant"', 'class="field gap-2"', 'class="table table-stack"'], false)
        ->getContent();

    expect($html)->not->toContain('<h3 class="text-lg font-semibold">')
        ->and($html)->toContain('<script src="'.asset('js/driver-form-validation.js').'"></script>');
});

it('keeps every field in the same order with the same names and ids', function () {
    $experience = collect(range(1, 7))->flatMap(fn () => ['equipment_class[]', 'experience[]', 'experience_from_date[]', 'experience_to_date[]', 'approx_miles[]'])->all();

    expect(step1Fields(step1Page()->assertOk()->getContent()))->toBe([
        'first_name#first_name', 'middle_name#middle_name', 'last_name#last_name', 'suffix#suffix',
        'business_name#business_name', 'employer_identification_number#employer_identification_number',
        'federal_tax_classification', 'federal_tax_classification', 'federal_tax_classification', 'federal_tax_classification',
        'date_of_birth#date_of_birth', 'ssn#ssn', 'main_phone#main_phone', 'alt_phone#alt_phone', 'email#email',
        'address#address', 'city#city', 'country#country', 'state#state', 'postal_code#postal_code',
        'twic_card', 'passport', 'photo#photo', 'same_address#same_address_checkbox',
        'residence_address[]#residence_address_0', 'residence_city[]#residence_city_0', 'residence_country[]#residence_country_0',
        'residence_state[]#residence_state_0', 'residence_postal_code[]#residence_postal_code_0',
        'license_first_name#license_first_name', 'license_last_name#license_last_name', 'license_issued#license_issued',
        'license_expires#license_expires', 'license_country#license_country', 'license_state#license_state',
        'license_class#license_class', 'license_number#license_number', 'repeat_license_number#repeat_license_number',
        'is_h_placarded_hazmat', 'is_n_tank_vehicle', 'is_p_passengers', 'is_t_double_trailer', 'is_s_school_bus', 'is_x_placarded_hazmat',
        ...$experience,
        'accident', 'accident', 'accident_date[]', 'accident_location[]', 'number_of_injuries[]', 'number_of_fatalities[]', 'hazmat_spill[]',
        'violation', 'violation', 'violation_date[]', 'violation_location[]', 'offense[]', 'vehicle_type[]',
        'denied_license', 'denied_license', 'license_revoked', 'license_revoked', 'forfeitures#forfeitures',
        'employer_name[]#employer_name_0', 'employer_record_address[]#employer_record_address_0', 'employer_record_city[]#employer_record_city_0',
        'employer_record_country[]#employer_record_country_0', 'employer_record_state[]#employer_record_state_0',
        'employer_record_postal_code[]#employer_record_postal_code_0', 'employer_record_phone[]#employer_record_phone_0',
        'employer_record_fax[]#employer_record_fax_0', 'employer_record_email[]#employer_record_email_0',
        'employer_record_position[]#employer_record_position_0', 'employer_record_date_from[]#employer_record_date_from_0',
        'employer_record_date_to[]#employer_record_date_to_0', 'employer_record_reason_for_leaving[]#employer_record_reason_for_leaving_0',
        'employed_regulations[]', 'employed_regulations[]', 'safety_sensitive_function[]', 'safety_sensitive_function[]',
    ]);
});

it('keeps the required fields, the radio defaults and the repeater templates', function () {
    $html = step1Page()->assertOk()->getContent();

    foreach (['first_name', 'last_name', 'date_of_birth', 'ssn', 'main_phone', 'email', 'address', 'city', 'postal_code',
        'license_first_name', 'license_last_name', 'license_issued', 'license_expires', 'license_number', 'repeat_license_number'] as $field) {
        expect($html)->toMatch('~<input[^>]*name="'.$field.'"[^>]*required~');
    }
    foreach (['accident', 'violation', 'denied_license', 'license_revoked', 'employed_regulations[]', 'safety_sensitive_function[]'] as $question) {
        expect($html)->toMatch('~name="'.preg_quote($question, '~').'" value="no" checked~');
    }

    expect($html)->toContain('id="residence_address_${residenceCount}" name="residence_address[]"')
        ->toContain('<input type="date" name="accident_date[]"')
        ->toContain('<input type="date" name="violation_date[]"')
        ->toContain('id="employer_name_${employmentRecordCount}" name="employer_name[]"')
        ->toContain('Was your job designated as a safety sensitive function, in any DOT regulated mode, subject to the alcohol and controlled substances testing requirements required by 49 CFR Part 40?');
});

it('shows each validation error next to its field', function () {
    $errors = new MessageBag([
        'first_name' => 'The first name field is required.',
        'license_number' => 'The license number field is required.',
        'residence_address.0' => 'The residence address field is required.',
        'employer_name.0' => 'The employer name field is required.',
        'experience_from_date.1' => 'The date from must be a date.',
        'accident' => 'The accident field is required.',
        'federal_tax_classification' => 'The federal tax classification is invalid.',
    ]);

    $html = step1Page(['errors' => (new ViewErrorBag)->put('default', $errors), '_old_input' => ['first_name' => 'Janet', 'residence_address' => ['1 Main St']]])
        ->assertOk()
        ->assertSee(['value="Janet"', 'value="1 Main St"'], false)
        ->getContent();

    expect($html)
        ->toMatch('~id="first_name"[^>]*aria-invalid="true"[^>]*aria-describedby="first_name-error"~')
        ->toMatch('~<p role="alert"\s+id="first_name-error"\s+class="text-sm text-destructive">The first name field is required.</p>~')
        ->toMatch('~<p role="alert"\s+id="license_number-error"\s+class="text-sm text-destructive">The license number field is required.</p>~')
        ->toMatch('~id="residence_address_0"[^>]*aria-invalid="true"~')
        ->toMatch('~<p role="alert"\s+id="residence_address_0-error"\s+class="text-sm text-destructive">The residence address field is required.</p>~')
        ->toMatch('~<p role="alert"\s+id="employer_name_0-error"\s+class="text-sm text-destructive">The employer name field is required.</p>~')
        ->toMatch('~Truck-Tractor date from"\s+aria-invalid="true"[^>]*>\s*<p role="alert" class="mt-1 text-xs text-destructive">The date from must be a date.</p>~')
        ->toMatch('~Have you had any accidents/crashes.*?<p role="alert" class="text-sm text-destructive">The accident field is required.</p>~s')
        ->toMatch('~Federal Tax Classification</legend>.*?The federal tax classification is invalid.~s');
});

it('still renders the admin step 1 edit form (the batch 2 driver form, not this view)', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();

    $this->actingAs($owner)->get(route('admin.driver.edit', $driver))->assertOk()
        ->assertSee('name="residence_address[', false)
        ->assertDontSee('name="residence_address[]"', false);
});
