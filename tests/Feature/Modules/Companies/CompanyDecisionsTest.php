<?php

use App\Models\PolicyPdf;
use Database\Factories\DriverFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Companies\Companies;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| Product decisions of 2026-10-11 (docs/testing/PRODUCT_DECISIONS.md):
| PO-14  policy PDFs per company (the platform-set fallback is PENDING, see below).
| PO-22  "Remove current logo" works and a replaced logo's file is deleted; an owner changing
|        their login email on Profile keeps companies.email in sync.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

describe('PO-14: policy PDFs per company', function () {
    it('lets a company owner upload and replace their own set', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);

        $this->actingAs($owner)->get(route('admin.settings.policy.pdf'))->assertOk();
        $this->actingAs($owner)->post(route('admin.settings.policy.pdf.store'), ['alcohol_drug_test_policy_pdf' => Companies::pdf()]);
        $first = Files::pathOf(PolicyPdf::where('company_id', $company->id)->sole()->alcohol_drug_test_policy_file_id);
        $this->actingAs($owner)->post(route('admin.settings.policy.pdf.store'), ['alcohol_drug_test_policy_pdf' => Companies::pdf()]);
        $second = Files::pathOf(PolicyPdf::where('company_id', $company->id)->sole()->alcohol_drug_test_policy_file_id);

        expect($first)->toStartWith('companies/'.$company->id.'/policies/')
            ->and($second)->not->toBe($first);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    });

    // The platform set moved to site_settings (F12) and has no upload screen yet
    it('falls back to the platform policy set when a company has none')->todo('PENDING: platform default policy PDFs');

    it('resolves each policy to the company\'s own file only', function () {
        $company = Actors::companyOf(Actors::companyOwner());
        $other = Actors::companyOf(Actors::companyOwner());
        $file = Files::stored($company->id);
        PolicyPdf::create(['company_id' => $company->id, 'alcohol_drug_test_policy_file_id' => $file->id]);

        expect(PolicyPdf::resolveFor($company->id)->alcohol_drug_test_policy_file_id)->toBe($file->id)
            ->and(PolicyPdf::resolveFor($company->id)->general_work_policy_file_id)->toBeNull()
            ->and(PolicyPdf::resolveFor($other->id)->alcohol_drug_test_policy_file_id)->toBeNull();
    });

    it('shows staff the policy of the driver\'s company in the wizard', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $driver = DriverFactory::new()->forCompany($company)->create();
        PolicyPdf::create(['company_id' => $company->id, 'general_work_policy_file_id' => Files::stored($company->id, 'own-general-work')->id]);

        $this->actingAs($owner)->get(route('admin.driver.general.work.policy', $driver->id))
            ->assertOk()
            ->assertSee(route('admin.settings.policy.pdf.file', 'general_work'), false)
            ->assertDontSee('/storage/', false);
        expect($this->actingAs($owner)->get(route('admin.settings.policy.pdf.file', 'general_work'))->streamedContent())->toBe('own-general-work');
    });
});

describe('PO-22: company logo', function () {
    it('removes the current logo and its file when "Remove current logo" is ticked', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        Storage::disk('public')->put('companies/logos/old.png', 'png');
        $company->update(['logo_path' => 'companies/logos/old.png']);

        $this->actingAs($owner)
            ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['remove_logo' => '1']))
            ->assertSessionHasNoErrors();

        expect($company->fresh()->logo_path)->toBeNull();
        Storage::disk('public')->assertMissing('companies/logos/old.png');
    });

    it('deletes the old file when the logo is replaced', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        Storage::disk('public')->put('companies/logos/old.png', 'png');
        $company->update(['logo_path' => 'companies/logos/old.png']);

        $this->actingAs($owner)
            ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, [
                'logo' => UploadedFile::fake()->image('new.png', 32, 32),
            ]))
            ->assertSessionHasNoErrors();

        $logo = $company->fresh()->logo_path;
        expect($logo)->not->toBe('companies/logos/old.png');
        Storage::disk('public')->assertMissing('companies/logos/old.png');
        Storage::disk('public')->assertExists($logo);
    });

    it('keeps the logo when the form is saved without changes', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        Storage::disk('public')->put('companies/logos/old.png', 'png');
        $company->update(['logo_path' => 'companies/logos/old.png']);

        $this->actingAs($owner)
            ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company))
            ->assertSessionHasNoErrors();

        expect($company->fresh()->logo_path)->toBe('companies/logos/old.png');
        Storage::disk('public')->assertExists('companies/logos/old.png');
    });
});

// PO-22 tied the company email to the owner's login email; D3b (14 §1) separated them.
describe('PO-22 as changed by D3b: owner login email', function () {
    it('leaves the company email alone when the owner changes their login email', function () {
        $owner = Actors::companyOwner();
        $companyEmail = Actors::companyOf($owner)->email;

        $this->actingAs($owner)
            ->patch(route('profile.update'), ['name' => $owner->name, 'email' => 'new-owner@example.test'])
            ->assertSessionHasNoErrors();

        expect($owner->fresh()->email)->toBe('new-owner@example.test')
            ->and(Actors::companyOf($owner)->fresh()->email)->toBe($companyEmail);
    });

    it('refuses a login email another user has, but not another company contact email', function () {
        $owner = Actors::companyOwner();
        $otherOwner = Actors::companyOwner();
        Actors::companyOf($otherOwner)->update(['email' => 'contact@example.test']);

        $this->actingAs($owner)
            ->patch(route('profile.update'), ['name' => $owner->name, 'email' => $otherOwner->email])
            ->assertSessionHasErrors('email');
        $this->actingAs($owner)
            ->patch(route('profile.update'), ['name' => $owner->name, 'email' => 'contact@example.test'])
            ->assertSessionHasNoErrors();

        expect($owner->fresh()->email)->toBe('contact@example.test');
    });
});
