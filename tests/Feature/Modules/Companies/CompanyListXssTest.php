<?php

use Database\Factories\CompanyFactory;
use Tests\Support\Actors;

/*
| CMP-01: the super-admin company list rendered company_name raw inside the logo alt attribute,
| and a tenant can set its own company_name. The apply URL was also injected into an inline
| onclick handler. Raw columns must be escaped and the URL passed through a data-* attribute.
*/

function companyListRow($test, $admin, int $companyId): array
{
    $response = $test->actingAs($admin)
        ->getJson(route('admin.settings.company'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    return collect($response->json('data'))->firstWhere('id', $companyId);
}

it('escapes the company name in the logo alt attribute', function () {
    $admin = Actors::superAdmin();
    $company = CompanyFactory::new()->create([
        'company_name' => 'Evil" onerror="alert(1)',
        'logo' => 'images/companies/logos/x.png',
    ]);

    $row = companyListRow($this, $admin, $company->id);

    expect($row['logo'])->not->toContain('" onerror="')
        ->and($row['logo'])->toContain('alt="Evil&quot; onerror=&quot;alert(1)"');
});

it('escapes the company name, phone and city columns', function () {
    $admin = Actors::superAdmin();
    $company = CompanyFactory::new()->create([
        'company_name' => '<script>alert(1)</script>',
        'city' => '<img src=x onerror=alert(2)>',
    ]);

    $row = companyListRow($this, $admin, $company->id);

    expect($row['company_name'])->not->toContain('<script>')
        ->and($row['city'])->not->toContain('<img');
});

it('passes the apply URL through a data attribute, not an inline onclick', function () {
    $admin = Actors::superAdmin();
    $company = CompanyFactory::new()->create(['slug' => "x');alert(1);//"]);

    $row = companyListRow($this, $admin, $company->id);

    expect($row['apply_url'])->not->toContain('onclick=')
        ->and($row['apply_url'])->not->toContain("x');alert(1)")
        ->and($row['apply_url'])->toContain('data-url="');
});
