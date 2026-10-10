<?php

use App\Models\Company;
use Database\Factories\CompanyFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Companies\Companies;
use Tests\Support\Actors;

/*
| CMP-04: the logo accepted SVG (served from /storage, so a script inside it runs on the app
| origin) and was stored under the client's extension. companies.slug had no unique index, so
| two companies could share a slug and /{slug}/apply resolved to whichever row came first.
*/

beforeEach(function () {
    Storage::fake('public');
});

it('rejects an SVG logo on create', function () {
    $this->actingAs(Actors::superAdmin())
        ->post(route('admin.settings.company.store'), Companies::storePayload(['logo' => Companies::svg()]))
        ->assertSessionHasErrors('logo');

    expect(Company::where('email', 'owner@acme-freight.test')->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('rejects an SVG logo on update', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['logo' => Companies::svg()]))
        ->assertSessionHasErrors('logo');

    expect($company->fresh()->logo)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('rejects an HTML file renamed to .png', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, [
            'logo' => Companies::realUpload('logo.png', '<html><script>alert(1)</script></html>'),
        ]))
        ->assertSessionHasErrors('logo');

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('stores an uploaded logo under a content-derived name', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, [
            'logo' => UploadedFile::fake()->image('my logo.php.png', 64, 64),
        ]))
        ->assertSessionHasNoErrors();

    $logo = $company->fresh()->logo;

    expect($logo)->not->toBeNull()
        ->and(basename($logo))->not->toContain('my')
        ->and(basename($logo))->toMatch('/^[A-Za-z0-9]{40}\.png$/');
    Storage::disk('public')->assertExists($logo);
});

it('stores a logo on create under a content-derived name', function () {
    $this->actingAs(Actors::superAdmin())
        ->post(route('admin.settings.company.store'), Companies::storePayload(['logo' => UploadedFile::fake()->image('logo.png')]))
        ->assertSessionHasNoErrors();

    $logo = Company::where('email', 'owner@acme-freight.test')->value('logo');

    expect(basename($logo))->toMatch('/^[A-Za-z0-9]{40}\.png$/');
    Storage::disk('public')->assertExists($logo);
});

it('gives a new company a unique slug when the name is taken', function () {
    CompanyFactory::new()->create(['company_name' => 'Acme Freight', 'slug' => 'acme-freight']);

    $this->actingAs(Actors::superAdmin())
        ->post(route('admin.settings.company.store'), Companies::storePayload())
        ->assertSessionHasNoErrors();

    expect(Company::where('email', 'owner@acme-freight.test')->value('slug'))->toBe('acme-freight-1');
});

it('enforces unique slugs in the database', function () {
    CompanyFactory::new()->create(['slug' => 'same-slug']);

    expect(fn () => CompanyFactory::new()->create(['slug' => 'same-slug']))
        ->toThrow(QueryException::class);
});
