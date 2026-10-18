<?php

use App\Models\DriverDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\Modules\Drivers\DriverAdmin;
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
    ][$step];
}

function adminStepPage(int $step, string $query = '', ?User $user = null)
{
    return test()->actingAs($user ?? test()->owner)->get(route(adminStep($step)[0], test()->driver->id).$query);
}

dataset('admin steps', [2, 3, 4]);

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
        ->assertSee(['Document Edit Process', 'Edit Mode - Step '.$step.' of 10', 'Back to Driver Edit', 'Skip to Next Step', 'Update & Continue to Step '.($step + 1)])
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
