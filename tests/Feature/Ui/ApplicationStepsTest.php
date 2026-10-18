<?php

use App\Models\Driver;
use App\Models\DriverDocument;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\PublicApplication\Applicant;

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

dataset('migrated steps', [2, 3, 4, 5, 6]);

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
        // Migrated steps follow light/dark/system; the theme lock is only for unmigrated ones.
        ->and($html)->toContain('(document.documentElement, "system", null)');
})->with('migrated steps');

it('keeps every field the step posts, the action and the hidden values', function (int $step) {
    $response = stepPage($step)->assertOk()
        ->assertSee('action="'.route('public.application.store.step'.$step, ['slug' => $this->company->slug]).'"', false)
        ->assertSee(['name="_token"', 'name="driver_id" value="'.$this->driver->id.'"', 'name="from_edit" value="0"', 'name="action" value="save"'], false);

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
})->with([6]);

it('keeps the old input on the medical card date', function () {
    stepPage(3, ['_old_input' => ['medical_certificate_expiration_date' => '2030-01-31']])
        ->assertOk()
        ->assertSee('value="2030-01-31"', false);
});

it('renders the normal step instead of a 500 for ?edit=1, without admin links', function (int $step) {
    $this->withSession(Applicant::session($this->driver))->get(stepUrl($step, $this->driver, '?edit=1'))
        ->assertOk()
        ->assertSee('name="from_edit" value="0"', false)
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
