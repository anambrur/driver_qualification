<?php

use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\PolicyPdf;
use App\Models\Violation;
use Database\Factories\LicenseFactory;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\PublicApplication\Applicant;
use Tests\Support\Files;

/*
| UI-14 (phase 2, batch 4d): the 10 public application steps on x-application.step, with the same
| form fields, actions and hidden values as before (Applicant::stepPayload is the field contract).
*/

beforeEach(function () {
    Applicant::fakeOtp();
    $this->company = Applicant::company();
    $this->driver = Applicant::draft($this->company, ['first_name' => 'Jane', 'last_name' => 'Roe']);
    DriverDocument::create(['driver_id' => $this->driver->id]);
});

function stepUrl(int $step, Driver $driver, string $query = ''): string
{
    $slug = $driver->company->slug;

    return ($step === 1
        ? route('public.application.step1', $slug)
        : route('public.application.step'.$step, ['slug' => $slug, 'driver_id' => $driver->id])).$query;
}

function stepPage(int $step, array $session = [])
{
    return test()->withSession(Applicant::session(test()->driver) + $session)->get(stepUrl($step, test()->driver));
}

dataset('migrated steps', [2, 3, 4, 5, 6, 7, 8, 9, 10]);

it('renders the step on the shared shell without Font Awesome, SweetAlert or inline styles', function (int $step) {
    $html = stepPage($step)->assertOk()
        ->assertSee('Step '.$step.' of 10')
        ->assertSee('aria-current="step"', false)
        ->assertSee('id="step-form"', false)
        ->assertSee('aria-label="Change colour theme"', false)
        ->assertDontSee(['kit.fontawesome.com', 'class="fas ', 'class="fa ', 'Swal.', 'sweetalert2'], false)
        ->getContent();

    preg_match_all('~<style[^>]*>(.*?)</style>~s', $html, $styles);
    expect($styles[1])->toHaveCount(1)
        ->and(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('(document.documentElement, "system")');
})->with('migrated steps');

it('keeps every field the step posts, the action and the hidden values', function (int $step) {
    $response = stepPage($step)->assertOk()
        ->assertSee('action="'.route('public.application.store.step'.$step, ['slug' => $this->company->slug]).'"', false)
        ->assertSee(['name="_token"', 'name="driver_id" value="'.$this->driver->id.'"', 'name="action" value="save"'], false);

    foreach (array_keys(Applicant::stepPayload($step, $this->driver)) as $field) {
        $response->assertSee('name="'.$field.'"', false);
    }
})->with('migrated steps');

it('shows server errors on the upload and in the summary', function () {
    stepPage(2, ['errors' => (new ViewErrorBag)->put('default', new MessageBag(['license_front' => 'The front image is required.']))])
        ->assertOk()
        ->assertSee('Please fix the following errors:')
        ->assertSee('id="license_front-error"', false)
        ->assertSee('aria-invalid="true"', false);
});

it('posts uploads as multipart', function (int $step) {
    stepPage($step)->assertOk()->assertSee('enctype="multipart/form-data"', false);
})->with([2, 3, 4]);

it('keeps the step 5 violation rows, radios and signature', function () {
    stepPage(5)->assertOk()
        ->assertSee(['id="violation_fields"', 'id="violation_add"', 'id="violation_remove"'], false)
        ->assertSee(['name="violation_date[]"', 'name="violation_location[]"', 'name="offense[]"', 'name="vehicle_type[]"'], false)
        ->assertSee(['name="violation" value="no"', 'name="violation" value="yes"'], false)
        ->assertSee('value="'.date('Y-m-d').'"', false);
});

it('keeps the step 6 answers checked from old input', function () {
    stepPage(6, ['_old_input' => ['drug_test_question_1' => 'no', 'drug_test_question_2' => 'n/a']])
        ->assertOk()
        ->assertSee('name="drug_test_question_2" value="n/a" class="input" checked', false)
        ->assertSee('name="drug_test_question_1" value="no" class="input" checked', false);
});

it('renders steps 6 to 10 even before the driver has a document row', function (int $step) {
    DriverDocument::query()->where('driver_id', $this->driver->id)->delete();

    stepPage($step)->assertOk();
})->with([6, 7, 8, 9, 10]);

it('keeps the old input on the medical card date', function () {
    stepPage(3, ['_old_input' => ['medical_certificate_expiration_date' => '2030-01-31']])
        ->assertOk()
        ->assertSee('value="2030-01-31"', false);
});

it('renders the normal step instead of a 500 for ?edit=1, without admin links', function (int $step) {
    $this->withSession(Applicant::session($this->driver))->get(stepUrl($step, $this->driver, '?edit=1'))
        ->assertOk()
        ->assertDontSee('name="from_edit"', false)
        ->assertDontSee('/admin/', false)
        ->assertDontSee('Skip to Next Step');
})->with('migrated steps');

it('keeps the admin edit links in the shared progress bar', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();

    $this->actingAs($owner)->get(route('admin.driver.license', $driver->id).'?edit=1')
        ->assertOk()
        ->assertSee('Document Edit Process')
        ->assertSee(route('admin.driver.medical.card', ['driver_id' => $driver->id]).'?edit=1', false)
        ->assertSee(route('admin.driver.edit', ['id' => $driver->id]), false);
});

it('asks for consent with the agreement box on steps 7 and 8', function (int $step, string $box) {
    stepPage($step)->assertOk()
        ->assertSee('name="'.$box.'" value="1"', false)
        ->assertSee(['name="date_signed"', 'value="'.date('Y-m-d').'"'], false);
})->with([[7, 'consent_agreement'], [8, 'authorization_agreement']]);

it('keeps the consent type on step 7', function () {
    stepPage(7)->assertOk()->assertSee('name="consent_type" value="multiple_unlimited"', false);
});

it('shows the policy PDF viewer and download when the company has one', function (int $step, string $column, string $policy) {
    PolicyPdf::create(['company_id' => $this->company->id, $column => Files::stored($this->company->id)->id]);
    $url = route('public.application.policy', [$this->company->slug, $policy]);

    stepPage($step)->assertOk()
        ->assertSee('<iframe src="'.$url.'"', false)
        ->assertSee('href="'.$url.'"', false)
        ->assertDontSee('Policy Document Not Available');
})->with([
    [9, 'alcohol_drug_test_policy_file_id', 'alcohol_drug_test'],
    [10, 'general_work_policy_file_id', 'general_work'],
]);

it('says when the policy PDF is missing', function (int $step) {
    stepPage($step)->assertOk()->assertSee('Policy Document Not Available')->assertDontSee('<iframe', false);
})->with([9, 10]);

it('no longer logs the driver name to the console on step 10', function () {
    stepPage(10)->assertOk()->assertDontSee('Driver name loaded', false);
});

it('renders step 1 on the shared shell with every field it posts', function () {
    $html = stepPage(1)->assertOk()
        ->assertSee('Step 1 of 10')
        ->assertSee(['id="driverForm"', 'enctype="multipart/form-data"', 'name="_token"'], false)
        ->assertSee('action="'.route('public.application.store.step1', ['slug' => $this->company->slug]).'"', false)
        ->assertDontSee(['kit.fontawesome.com', 'class="fas ', 'Swal.'], false)
        ->getContent();

    // The payload also sends medical_certificate_expiration_date, which step 1 never rendered (step 3 asks for it).
    foreach (array_diff(array_keys(Applicant::step1Payload($this->driver)), ['medical_certificate_expiration_date']) as $field) {
        expect($html)->toMatch('~name="'.preg_quote($field, '~').'(\[\])?"~');
    }
    expect(substr_count($html, '<h1'))->toBe(1);
});

it('keeps the step 1 repeaters, jump menus and old input', function () {
    stepPage(1, ['_old_input' => ['first_name' => 'Janet', 'city' => 'Austin']])
        ->assertOk()
        ->assertSee(['id="residence_fields"', 'id="residence_add"', 'id="accident_fields"', 'id="violation_fields"', 'id="employment_record_fields"', 'id="employment_record_add"'], false)
        ->assertSee(['id="driver-section-jump"', 'id="driver-section-jump-mobile"', 'id="section-applicant"'], false)
        ->assertSee(['value="Janet"', 'value="Austin"'], false);
});

it('prefills the medical card expiry saved on step 3', function () {
    Storage::fake('local');
    $this->seed(DocumentTypeSeeder::class);
    $session = Applicant::session($this->driver);

    $this->withSession($session)
        ->post(route('public.application.store.step3', $this->company->slug), ['medical_certificate_expiration_date' => '2031-05-06'] + Applicant::stepPayload(3, $this->driver))
        ->assertSessionHasNoErrors();

    $this->withSession($session)->get(stepUrl(3, $this->driver))->assertOk()
        ->assertSee('value="2031-05-06"', false)
        ->assertSee('Medical Certificate Information');
});

it('shows the company contact on the policy steps', function (int $step) {
    // A fixed name: a random one with an apostrophe is JSON-escaped as \u0027 on the page
    $this->company->forceFill(['company_name' => 'Acme Freight', 'phone' => '(209) 555-0142', 'email' => 'hr@acme.test'])->save();
    PolicyPdf::create(['company_id' => $this->company->id, ($step === 9 ? 'alcohol_drug_test' : 'general_work').'_policy_file_id' => Files::stored($this->company->id)->id]);

    stepPage($step)->assertOk()
        ->assertSee('Need help? Call (209) 555-0142 or email hr@acme.test')
        ->assertSee(json_encode($this->company->company_name), false)
        ->assertDontSee('N/A or email');
})->with([9, 10]);

it('sends Finish Later to the company application page after a confirm', function () {
    stepPage(4)->assertOk()
        ->assertSee('href="'.route('application.form', $this->company->slug).'"', false)
        ->assertSee('data-confirm="Finish later?"', false)
        ->assertSee('Finish Later')
        ->assertDontSee('Save & Finish Later');
});

it('shows no inert Finish Later button on the admin wizard', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();

    $this->actingAs($owner)->get(route('admin.driver.license', $driver->id))
        ->assertOk()
        ->assertSee('Application Process')
        ->assertDontSee('Finish Later');
});

it('prefills step 5 from driver_documents.has_violations and the saved rows', function () {
    DriverDocument::query()->where('driver_id', $this->driver->id)->update(['has_violations' => true, 'violation_record_signature' => 'Jane Roe', 'violation_record_date_signed' => '2026-01-01']);
    Violation::create(['driver_id' => $this->driver->id, 'violation_date' => '2025-05-01', 'violation_location' => 'Austin', 'offense' => 'Speeding', 'vehicle_type' => 'Truck']);

    $html = stepPage(5)->assertOk()
        ->assertSee(['name="violation_date[]" value="2025-05-01"', 'value="Speeding"', 'Current Violation Information'], false)
        ->getContent();

    expect($html)->toMatch('~name="violation" value="yes" class="input"\\s+checked~');
});

it('checks no violations on step 5 when the record says none', function () {
    DriverDocument::query()->where('driver_id', $this->driver->id)->update(['has_violations' => false, 'violation_record_signature' => 'Jane Roe', 'violation_record_date_signed' => '2026-01-01']);

    expect(stepPage(5)->assertOk()->assertSee(['No violations recorded for this driver.', '01/01/2026'])->getContent())
        ->toMatch('~name="violation" value="no" class="input"\\s+checked~');
});

it('shows the license state from state_code on step 2', function () {
    LicenseFactory::new()->create(['driver_id' => $this->driver->id, 'state_code' => 'NV']);

    expect(stepPage(2)->assertOk()->getContent())->toMatch('~<dt[^>]*>State</dt>\\s*<dd[^>]*>\\s*NV\\s*</dd>~');
});

it('has no edit-mode branches left in the step scripts', function (int $step) {
    stepPage($step)->assertOk()->assertDontSee('isEditMode', false);
})->with('migrated steps');
