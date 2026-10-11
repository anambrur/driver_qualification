<?php

use App\Models\PolicyPdf;
use Database\Factories\DriverFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Companies\Companies;
use Tests\Support\Actors;

/*
| Product decisions of 2026-10-11 (docs/testing/PRODUCT_DECISIONS.md):
| PO-14  policy PDFs per company, with the platform set as fallback.
| PO-22  "Remove current logo" works and a replaced logo's file is deleted; an owner changing
|        their login email on Profile keeps companies.email in sync.
*/

beforeEach(function () {
    Storage::fake('public');
});

describe('PO-14: policy PDFs per company', function () {
    it('lets a company owner upload and replace their own set', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);

        $this->actingAs($owner)->get(route('admin.settings.policy.pdf'))->assertOk();
        $this->actingAs($owner)->post(route('admin.settings.policy.pdf.store'), ['alcohol_drug_test_policy_pdf' => Companies::pdf()]);
        $first = PolicyPdf::where('company_id', $company->id)->sole()->alcohol_drug_test_policy_pdf;
        $this->actingAs($owner)->post(route('admin.settings.policy.pdf.store'), ['alcohol_drug_test_policy_pdf' => Companies::pdf()]);
        $second = PolicyPdf::where('company_id', $company->id)->sole()->alcohol_drug_test_policy_pdf;

        expect($first)->toStartWith('policy_pdfs/'.$company->id.'/')
            ->and($second)->not->toBe($first);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    });

    it('keeps the super-admin on the platform set', function () {
        $this->actingAs(Actors::superAdmin())->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()]);

        expect(PolicyPdf::sole()->company_id)->toBeNull();
    });

    it('resolves each policy to the company\'s own file, or the platform one', function () {
        $company = Actors::companyOf(Actors::companyOwner());
        $other = Actors::companyOf(Actors::companyOwner());
        PolicyPdf::create(['company_id' => null, 'alcohol_drug_test_policy_pdf' => 'policy_pdfs/platform-a.pdf', 'general_work_policy_pdf' => 'policy_pdfs/platform-g.pdf']);
        PolicyPdf::create(['company_id' => $company->id, 'alcohol_drug_test_policy_pdf' => 'policy_pdfs/'.$company->id.'/own-a.pdf']);

        $mine = PolicyPdf::resolveFor($company->id);
        $theirs = PolicyPdf::resolveFor($other->id);

        expect($mine->alcohol_drug_test_policy_pdf)->toBe('policy_pdfs/'.$company->id.'/own-a.pdf')
            ->and($mine->general_work_policy_pdf)->toBe('policy_pdfs/platform-g.pdf')
            ->and($theirs->alcohol_drug_test_policy_pdf)->toBe('policy_pdfs/platform-a.pdf');
    });

    it('shows a driver the policy of the driver\'s company in the wizard', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $driver = DriverFactory::new()->create(['company_id' => $company->id]);
        PolicyPdf::create(['company_id' => null, 'general_work_policy_pdf' => 'policy_pdfs/platform-g.pdf']);
        PolicyPdf::create(['company_id' => $company->id, 'general_work_policy_pdf' => 'policy_pdfs/'.$company->id.'/own-g.pdf']);

        $this->actingAs($owner)->get(route('admin.driver.general.work.policy', $driver->id))
            ->assertOk()
            ->assertSee('policy_pdfs/'.$company->id.'/own-g.pdf', false)
            ->assertDontSee('platform-g.pdf', false);
    });
});

describe('PO-22: company logo', function () {
    it('removes the current logo and its file when "Remove current logo" is ticked', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        Storage::disk('public')->put('companies/logos/old.png', 'png');
        $company->update(['logo' => 'companies/logos/old.png']);

        $this->actingAs($owner)
            ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, ['remove_logo' => '1']))
            ->assertSessionHasNoErrors();

        expect($company->fresh()->logo)->toBeNull();
        Storage::disk('public')->assertMissing('companies/logos/old.png');
    });

    it('deletes the old file when the logo is replaced', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        Storage::disk('public')->put('companies/logos/old.png', 'png');
        $company->update(['logo' => 'companies/logos/old.png']);

        $this->actingAs($owner)
            ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company, [
                'logo' => UploadedFile::fake()->image('new.png', 32, 32),
            ]))
            ->assertSessionHasNoErrors();

        $logo = $company->fresh()->logo;
        expect($logo)->not->toBe('companies/logos/old.png');
        Storage::disk('public')->assertMissing('companies/logos/old.png');
        Storage::disk('public')->assertExists($logo);
    });

    it('keeps the logo when the form is saved without changes', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        Storage::disk('public')->put('companies/logos/old.png', 'png');
        $company->update(['logo' => 'companies/logos/old.png']);

        $this->actingAs($owner)
            ->patch(route('admin.settings.company.update', $company->id), Companies::updatePayload($company))
            ->assertSessionHasNoErrors();

        expect($company->fresh()->logo)->toBe('companies/logos/old.png');
        Storage::disk('public')->assertExists('companies/logos/old.png');
    });
});

describe('PO-22: owner login email', function () {
    it('updates the company email when the owner changes their login email', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)
            ->patch(route('profile.update'), ['name' => $owner->name, 'email' => 'new-owner@example.test'])
            ->assertSessionHasNoErrors();

        expect($owner->fresh()->email)->toBe('new-owner@example.test')
            ->and(Actors::companyOf($owner)->fresh()->email)->toBe('new-owner@example.test');
    });

    it('refuses an email another company uses', function () {
        $owner = Actors::companyOwner();
        $other = Actors::companyOf(Actors::companyOwner());
        $other->update(['email' => 'taken@example.test']);

        $this->actingAs($owner)
            ->patch(route('profile.update'), ['name' => $owner->name, 'email' => 'taken@example.test'])
            ->assertSessionHasErrors('email');

        expect(Actors::companyOf($owner)->fresh()->email)->not->toBe('taken@example.test');
    });
});
