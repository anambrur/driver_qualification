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
| Companies are created by the platform (WP8a) without a logo; the owner sets it on update.
*/

beforeEach(function () {
    Storage::fake('public');
});

it('rejects an SVG logo on update', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);

    $this->actingAs($owner)
        ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['logo' => Companies::svg()]))
        ->assertSessionHasErrors('logo');

    expect($company->fresh()->logo_path)->toBeNull()
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

    $logo = $company->fresh()->logo_path;

    expect($logo)->not->toBeNull()
        ->and(basename($logo))->not->toContain('my logo')
        ->and(basename($logo))->toMatch('/^[A-Za-z0-9]{40}\.png$/');
    Storage::disk('public')->assertExists($logo);
});

it('gives a new company a unique slug when the name is taken', function () {
    CompanyFactory::new()->create(['company_name' => 'Acme Freight', 'slug' => 'acme-freight']);

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->post(route('platform.companies.store'), ['company_name' => 'Acme Freight', 'owner_name' => 'Ann Owner', 'owner_email' => 'owner@acme-freight.test'])
        ->assertSessionHasNoErrors();

    $slug = Company::query()->where('company_name', 'Acme Freight')->latest('id')->value('slug');
    expect($slug)->not->toBe('acme-freight')->toStartWith('acme-freight-');
});

it('enforces unique slugs in the database', function () {
    CompanyFactory::new()->create(['slug' => 'same-slug']);

    expect(fn () => CompanyFactory::new()->create(['slug' => 'same-slug']))
        ->toThrow(QueryException::class);
});
