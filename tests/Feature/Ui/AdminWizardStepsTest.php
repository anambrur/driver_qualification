<?php

use App\Models\DriverDocument;
use App\Models\PolicyPdf;
use App\Models\User;
use App\Models\Violation;
use Database\Factories\LicenseFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\PublicApplication\Applicant;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| UI-15 (phase 2, batch 6): admin wizard steps 2 to 10 on x-application.step (admin mode), with the same
| form fields, actions, hidden values and edit flow as before.
*/

beforeEach(function () {
    Storage::fake('local');
    [$this->owner, $this->driver] = DriverAdmin::tenantWithDriver(['first_name' => 'Jane', 'last_name' => 'Roe']);
});

function adminStep(int $step): array
{
    return [
        2 => ['admin.driver.license', 'admin.driver.license.store', ['license_front', 'license_back']],
        3 => ['admin.driver.medical.card', 'admin.driver.medical.card.store', ['medical_card', 'medical_certificate_expiration_date']],
        4 => ['admin.driver.forfeiture', 'admin.driver.forfeiture.store', ['forfeiture_document']],
        5 => ['admin.driver.violation', 'admin.driver.violation.store', ['violation', 'violation_date[]', 'violation_location[]', 'offense[]', 'vehicle_type[]', 'applicant_signature', 'date_signed']],
        6 => ['admin.driver.alcohol.and.drug.test', 'admin.driver.alcohol.and.drug.store', ['drug_test_question_1', 'drug_test_question_2', 'applicant_signature', 'date_signed']],
        7 => ['admin.driver.fmcsa.consent', 'admin.driver.fmcsa.consent.store', ['consent_type', 'consent_agreement', 'employee_signature', 'date_signed']],
        8 => ['admin.driver.psp', 'admin.driver.psp.store', ['authorization_agreement', 'applicant_signature', 'date_signed']],
        9 => ['admin.driver.alcohol.and.drug.test.policy', 'admin.driver.alcohol.and.drug.test.policy.store', ['employee_signature', 'date_signed']],
        10 => ['admin.driver.general.work.policy', 'admin.driver.general.work.policy.store', ['employee_signature', 'date_signed']],
    ][$step];
}

function adminStepPage(int $step, string $query = '', ?User $user = null)
{
    return test()->actingAs($user ?? test()->owner)->get(route(adminStep($step)[0], test()->driver->id).$query);
}

dataset('admin steps', [2, 3, 4, 5, 6, 7, 8, 9, 10]);

it('renders the step on the shared shell inside the admin layout', function (int $step) {
    adminStepPage($step)->assertOk()
        ->assertSee('id="step-form"', false)
        ->assertSee('aria-current="step"', false)
        ->assertSee(['Application Process', 'Driver Information', 'Driver: Jane Roe'])
        ->assertDontSee(['Step '.$step.' of 10</p>', 'Finish Later', 'Withdraw application?', 'Document Edit Process'], false);
})->with('admin steps');

it('keeps the action, the fields and the hidden values', function (int $step) {
    [, $store, $fields] = adminStep($step);

    $response = adminStepPage($step)->assertOk()
        ->assertSee('action="'.route($store).'"', false)
        ->assertSee(['name="_token"', 'name="driver_id" value="'.$this->driver->id.'"', 'name="from_edit" value="0"', 'name="action" value="save"'], false)
        ->assertDontSee('Skip to Next Step');

    foreach ($fields as $field) {
        $response->assertSee('name="'.$field.'"', false);
    }
})->with('admin steps');

it('keeps the edit flow: from_edit, the edit links, back to driver edit and skip', function (int $step) {
    adminStepPage($step, '?edit=1')->assertOk()
        ->assertSee(['name="from_edit" value="1"'], false)
        ->assertSee(['Document Edit Process', 'Edit Mode - Step '.$step.' of 10', 'Back to Driver Edit', $step === 10 ? 'Skip to Completion' : 'Skip to Next Step', $step === 10 ? 'Update & Complete Application' : 'Update & Continue to Step '.($step + 1)])
        ->assertSee(route('admin.driver.edit', ['id' => $this->driver->id]), false)
        ->assertSee(route('admin.driver.violation', ['driver_id' => $this->driver->id]).'?edit=1', false);
})->with('admin steps');

it('skips step 2 with an action=skip submit and steps 3 and 4 with a link to the next step', function () {
    adminStepPage(2, '?edit=1')->assertSee('name="action" value="skip"', false);
    adminStepPage(3, '?edit=1')->assertDontSee('value="skip"', false)
        ->assertSee('href="'.route('admin.driver.forfeiture', ['driver_id' => $this->driver->id, 'edit' => '1']).'"', false);
    adminStepPage(4, '?edit=1')->assertDontSee('value="skip"', false)
        ->assertSee('href="'.route('admin.driver.violation', ['driver_id' => $this->driver->id, 'edit' => '1']).'"', false);
});

it('binds the step script to the step form, not to the first form (the logout form)', function (int $step) {
    adminStepPage($step)->assertOk()
        ->assertSee("document.getElementById('step-form')", false)
        ->assertDontSee("document.querySelector('form')", false);
})->with('admin steps');

it('marks the uploads required unless an edit already has the file', function () {
    $cid = $this->driver->company_id;
    DriverDocument::create(['driver_id' => $this->driver->id, 'license_front_file_id' => Files::stored($cid, 'png', 'png')->id, 'medical_card_file_id' => Files::stored($cid)->id]);
    $required = fn (string $name, string $html) => (bool) preg_match('~<input id="'.$name.'"[^>]*\brequired\b~', $html);

    $create = adminStepPage(2)->getContent();
    $edit = adminStepPage(2, '?edit=1')->assertSee(['Current Front Image:', 'alt="License Front"', 'Replace Front Image', 'Upload Back Image'], false)->getContent();
    $medical = adminStepPage(3, '?edit=1')->assertSee(['Current Medical Card:', 'Medical Card Document', 'View Document'])->getContent();

    expect($required('license_front', $create))->toBeTrue()
        ->and($required('license_front', $edit))->toBeFalse()
        ->and($required('license_back', $edit))->toBeTrue()
        ->and($required('medical_card', $medical))->toBeFalse();
});

it('shows server errors in the summary and on the field', function () {
    $this->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['medical_card' => 'The medical card is required.']))]);

    adminStepPage(3)->assertOk()
        ->assertSee('Please fix the following errors:')
        ->assertSee(['id="medical_card-error"', 'aria-invalid="true"'], false);
});

it('forbids the wizard steps without drivers.create or drivers.edit', function (int $step) {
    $member = Actors::memberWithPermissions(Actors::companyOf($this->owner), ['drivers.view']);

    adminStepPage($step, '', $member)->assertForbidden();
})->with('admin steps');

it('keeps the step on theme tokens, without the TailAdmin scales', function (int $step) {
    $html = adminStepPage($step)->assertOk()->getContent();

    expect($html)->not->toMatch('~\b(?:text|bg|border)-(?:error|success|warning|purple|blue|red|green|yellow)-\d~');
})->with('admin steps');

it('prefills the step 5 violations from driver_documents.has_violations and the rows, with the license line', function () {
    LicenseFactory::new()->create(['driver_id' => $this->driver->id, 'license_number' => 'D7654321', 'state_code' => 'TX']);
    // The answer and the signature are on driver_documents; rows exist only for real violations
    DriverDocument::create(['driver_id' => $this->driver->id, 'has_violations' => true, 'violation_record_signature' => 'Jane Roe', 'violation_record_date_signed' => '2026-01-01']);
    Violation::create(['driver_id' => $this->driver->id, 'violation_date' => '2025-05-01', 'violation_location' => 'Austin', 'offense' => 'Speeding', 'vehicle_type' => 'Truck']);

    $page = adminStepPage(5, '?edit=1')->assertOk();
    expect($page->getContent())->toMatch('~name="violation" value="yes" class="input"\\s+checked~');
    $page
        ->assertSee(['id="violation_fields"', 'id="violation_add"', 'id="violation_remove"'], false)
        ->assertSee(['name="violation" value="no"', 'value="Speeding"', 'name="violation_date[]" value="2025-05-01"'], false)
        ->assertSee(['License: D7654321 (TX)', 'Current Violation Information', 'Has Violations', 'Last Signed'], false)
        ->assertSee('href="'.route('admin.driver.alcohol.and.drug.test', ['driver_id' => $this->driver->id, 'edit' => '1']).'"', false);
});

it('checks no violations and shows the signature when the record says none', function () {
    DriverDocument::create(['driver_id' => $this->driver->id, 'has_violations' => false, 'violation_record_signature' => 'Jane Roe', 'violation_record_date_signed' => '2026-01-01']);

    $html = adminStepPage(5, '?edit=1')->assertOk()
        ->assertSee(['No violations recorded for this driver.', 'Jane Roe', '01/01/2026'])
        ->getContent();

    expect($html)->toMatch('~name="violation" value="no" class="input"\\s+checked~')
        ->not->toMatch('~name="violation" value="yes" class="input"\\s+checked~');
});

it('shows the license state from state_code (licenses has no state column)', function () {
    LicenseFactory::new()->create(['driver_id' => $this->driver->id, 'license_number' => 'D7654321', 'state_code' => 'NV']);

    expect(adminStepPage(2)->assertOk()->assertSee(['Current License Information', 'D7654321'])->getContent())
        ->toMatch('~<dt[^>]*>State</dt>\\s*<dd[^>]*>\\s*NV\\s*</dd>~');
});

it('requires the step 6 answers and signature only when creating', function () {
    $create = adminStepPage(6)->assertOk()->getContent();
    $edit = adminStepPage(6, '?edit=1')->assertOk()->getContent();

    expect(preg_match_all('~name="drug_test_question_\d" value="[^"]+" class="input"\s+required~', $create))->toBe(5)
        ->and(preg_match_all('~name="drug_test_question_\d" value="[^"]+" class="input"\s+required~', $edit))->toBe(0)
        ->and($create)->toMatch('~<input[^>]*id="applicant_signature"[^>]*required~')
        ->and($edit)->not->toMatch('~<input[^>]*id="applicant_signature"[^>]*required~');
});

it('runs the warning for a changed positive drug test answer (it sat in a DOMContentLoaded that never fired)', function () {
    DriverDocument::create(['driver_id' => $this->driver->id, 'drug_test_question_1' => 'yes', 'drug_test_question_2' => 'no', 'drug_test_signature' => 'Jane Roe']);

    $html = adminStepPage(6, '?edit=1')->assertOk()
        ->assertSee(['Current Alcohol & Drug Test Information', 'You are changing a previously recorded positive test response'], false)
        ->getContent();

    expect($html)->toContain('const currentQ1')
        ->not->toMatch("~addEventListener\\('DOMContentLoaded', function\\(\\) \\{\\s*const currentQ1~");
});

/**
 * The legally fixed text between two phrases, as plain words.
 */
function legalText(string $html, string $from, string $to): string
{
    $text = preg_replace('~\s+~', ' ', html_entity_decode(strip_tags(preg_replace('~<script\b.*?</script>~s', '', $html)), ENT_QUOTES));
    $start = strpos($text, $from);
    $end = strpos($text, $to, (int) $start);
    expect($start)->not->toBeFalse()->and($end)->not->toBeFalse();

    return substr($text, $start, $end - $start + strlen($to));
}

it('keeps the legal text word for word the same as the public application step', function (int $step, string $from, string $to) {
    // The admin pages name the signed-in user as the employer and as the contact; give both the company's values
    $company = DriverAdmin::companyOf($this->owner);
    $company->forceFill(['company_name' => 'Acme Freight', 'phone' => '(209) 555-0142', 'email' => 'hr@acme.test'])->save();
    $this->owner->forceFill(['name' => 'Acme Freight', 'email' => 'hr@acme.test'])->save();
    Applicant::fakeOtp();
    $applicant = Applicant::draft($company, ['first_name' => 'Jane', 'last_name' => 'Roe']);
    DriverDocument::create(['driver_id' => $applicant->id]);
    $public = test()->withSession(Applicant::session($applicant))
        ->get(route('public.application.step'.$step, ['slug' => $company->slug, 'driver_id' => $applicant->id]))->assertOk()->getContent();

    expect(legalText(adminStepPage($step)->assertOk()->getContent(), $from, $to))->toBe(legalText($public, $from, $to));
})->with([
    'violation record and FCRA' => [5, 'I certify that the following', 'any other public records'],
    'drug test statement' => [6, '49 CFR Part 40.25(j) states', 'termination of employment.'],
    'FMCSA consent' => [7, 'hereby provide consent', 'for the duration of my employment.'],
    'PSP disclosure and authorization' => [8, 'In connection with your application', 'or any other language.'],
    'drug testing policy' => [9, 'Kindly go over', 'confirms my acknowledgment and agreement.'],
    'general work policy' => [10, 'Kindly explore', 'confirms my acknowledgment and agreement.'],
]);

it('keeps the admin employer wording: the signed-in user is named in the consent and PSP text', function () {
    $this->owner->forceFill(['name' => 'Pat Admin'])->save();

    adminStepPage(7)->assertSee('hereby provide consent to <strong>Pat Admin</strong>', false);
    adminStepPage(8)->assertSee('application for employment with <strong>Pat Admin ("Prospective Employer")</strong>', false);
});

it('shows the policy PDF from the admin settings route with the admin contact', function (int $step, string $column, string $policy) {
    $company = DriverAdmin::companyOf($this->owner);
    $company->forceFill(['phone' => '(209) 555-0142'])->save();
    PolicyPdf::create(['company_id' => $company->id, $column => Files::stored($company->id)->id]);
    $url = route('admin.settings.policy.pdf.file', $policy);

    adminStepPage($step)->assertOk()
        ->assertSee(['<iframe src="'.$url.'"', 'href="'.$url.'"'], false)
        ->assertSee('Need help? Call (209) 555-0142 or email '.$this->owner->email);
})->with([[9, 'alcohol_drug_test_policy_file_id', 'alcohol_drug_test'], [10, 'general_work_policy_file_id', 'general_work']]);

it('scopes the policy scripts to the step form, not the logout button or another iframe', function (int $step) {
    adminStepPage($step)->assertOk()
        ->assertSee(["document.querySelector('#step-form iframe')", "document.querySelector('#step-form button[type=\"submit\"]')", "document.getElementById('policy-card')"], false)
        ->assertDontSee("document.querySelector('button[type=\"submit\"]')", false);
})->with([9, 10]);

it('shows the current consent, authorization and acknowledgment when editing', function () {
    DriverDocument::create(['driver_id' => $this->driver->id, 'fmcsa_consent' => true, 'fmcsa_consent_signature' => 'Jane Roe', 'psp_authorization' => true, 'psp_authorization_signature' => 'Jane Roe',
        'alcohol_drug_test_policy_signature' => 'Jane Roe', 'general_work_policy_signature' => 'Jane Roe']);

    adminStepPage(7, '?edit=1')->assertSee(['Current FMCSA Consent Status', 'Consent Granted', 'Updating this form will create a new consent record.']);
    adminStepPage(8, '?edit=1')->assertSee(['Current PSP Authorization Status', 'Updating this form will create a new authorization record.']);
    adminStepPage(9, '?edit=1')->assertSee(['Current Policy Acknowledgment Status', 'Policy Acknowledged']);
    adminStepPage(10, '?edit=1')->assertSee(['Current Policy Acknowledgment Status', 'Skip to Completion']);
});
