<?php

use Database\Factories\CompanyFactory;
use Tests\Support\Actors;

/*
| CMP-01: the super-admin company list rendered company_name raw inside the logo alt attribute,
| and a tenant can set its own company_name. The apply URL was also injected into an inline
| onclick handler. The list is the platform's now (/platform/companies, WP8a), and its rows come
| from the server-side table (UI-08): tenant-controlled text must still be escaped there, and on
| the company's own pages.
*/

it('escapes tenant-controlled company fields in the platform list', function () {
    CompanyFactory::new()->create(['company_name' => '<script>alert(1)</script>', 'email' => 'x"><img src=x onerror=alert(2)>@a.test']);

    $row = $this->actingAs(Actors::platformOwner(), 'platform')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get(route('platform.companies.index', ['draw' => 1]))
        ->assertOk()->json('data.0.company');

    expect($row)->not->toContain('<script>alert(1)</script>')->not->toContain('<img')
        ->toContain(e('<script>alert(1)</script>'));
});

it('escapes the company name and slug on the company\'s own settings page', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $company->update(['company_name' => 'Evil" onerror="alert(1)', 'slug' => "x');alert(1);//"]);

    $this->actingAs($owner)
        ->get(route('admin.settings.company.edit', $company->id))
        ->assertOk()
        ->assertDontSee('Evil" onerror="alert(1)', false)
        ->assertDontSee("x');alert(1);//", false);
});
