<?php

use App\Models\User;
use Database\Factories\CompanyFactory;
use Tests\Feature\Modules\Companies\Companies;
use Tests\Support\Actors;

/*
| CMP-02: company owners have companies.edit so they can edit their own profile ("My Account"),
| but update() also let them change the company/user status (re-activating an account a
| super-admin disabled) and the login email (bypassing the Profile page's re-verification and
| the users.email unique check). Status and email are super-admin fields; owners keep the rest.
*/

it('does not let a company owner change their own status', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $company->update(['status' => 'inactive']);

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['status' => 'active']))
        ->assertRedirect();

    expect($company->fresh()->status)->toBe('inactive');
});

it('does not let a company owner change the account email', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $originalEmail = $owner->email;

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['email' => 'hijack@example.test']))
        ->assertRedirect();

    expect($company->fresh()->email)->toBe($originalEmail)
        ->and($owner->fresh()->email)->toBe($originalEmail);
});

it('still lets a company owner update the other profile fields', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['city' => 'Dallas', 'company_name' => 'Renamed Co']))
        ->assertRedirect(route('admin.settings.company.edit', $company->id));

    expect($company->fresh())
        ->city->toBe('Dallas')
        ->company_name->toBe('Renamed Co');
});

it('does not let a company owner edit another company', function () {
    $owner = Actors::companyOwner();
    $other = CompanyFactory::new()->create();

    $this->actingAs($owner)->get(route('admin.settings.company.edit', $other->id))->assertForbidden();
    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $other->id), Companies::updatePayload($other, ['city' => 'Hacked']))
        ->assertForbidden();

    expect($other->fresh()->city)->not->toBe('Hacked');
});

it('lets a super-admin change status and email', function () {
    $admin = Actors::superAdmin();
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);

    $this->actingAs($admin)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['status' => 'inactive', 'email' => 'new@example.test']))
        ->assertRedirect(route('admin.settings.company'));

    expect($company->fresh())
        ->status->toBe('inactive')
        ->email->toBe('new@example.test')
        ->and($owner->fresh())
        ->status->toBe('inactive')
        ->email->toBe('new@example.test');
});

it('rejects an email that already belongs to another user', function () {
    $admin = Actors::superAdmin();
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $someoneElse = User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($admin)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['email' => $someoneElse->email]))
        ->assertSessionHasErrors('email');

    expect($company->fresh()->email)->not->toBe('taken@example.test');
});

it('rejects a new company whose email already belongs to a user', function () {
    $admin = Actors::superAdmin();
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($admin)
        ->post(route('admin.settings.company.store'), Companies::storePayload(['email' => 'taken@example.test']))
        ->assertSessionHasErrors('email');
});
