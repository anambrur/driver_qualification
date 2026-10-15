<?php

use App\Models\User;
use Database\Factories\CompanyFactory;
use Tests\Feature\Modules\Companies\Companies;
use Tests\Support\Actors;

/*
| CMP-02: company owners have companies.edit so they can edit their own profile ("My Account"),
| but update() also let them change the company/user status (re-activating an account a
| super-admin disabled) and the login email (bypassing the Profile page's re-verification and
| the users.email unique check). Status is a platform action (/platform) and the login email
| belongs to Profile (D3b); owners keep the rest.
*/

it('does not let a company owner change their own status', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $company->update(['status' => 'suspended']);

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['status' => 'active']))
        ->assertRedirect();

    expect($company->fresh()->status)->toBe('suspended');
});

it('does not let a company owner change the account email', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    // The company contact email and the owner's login email are separate (D3b)
    $companyEmail = $company->email;
    $loginEmail = $owner->email;

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['email' => 'hijack@example.test']))
        ->assertRedirect();

    expect($company->fresh()->email)->toBe($companyEmail)
        ->and($owner->fresh()->email)->toBe($loginEmail);
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

    // Another company's id is not found (03 §5), not forbidden
    $this->actingAs($owner)->get(route('admin.settings.company.edit', $other->id))->assertNotFound();
    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $other->id), Companies::updatePayload($other, ['city' => 'Hacked']))
        ->assertNotFound();

    expect($other->fresh()->city)->not->toBe('Hacked');
});

// Status, email and new companies are platform actions: see Platform/PlatformAreaTest (companies).
