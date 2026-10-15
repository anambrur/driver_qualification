<?php

use App\Models\PolicyPdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Companies\Companies;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| CMP-05: the upload error path returned the raw exception message to the browser.
| Decided PO-14 (2026-10-11): each company uploads its own policy PDFs. They are private stored
| files (WP9), opened through admin.settings.policy.pdf.file and public.application.policy.
*/

beforeEach(function () {
    Storage::fake('local');
});

it('does not leak the exception message when the upload fails', function () {
    Log::spy();
    Storage::shouldReceive('disk')->andThrow(new RuntimeException('SQLSTATE secret /var/www/internal/path'));

    $response = $this->actingAs(Actors::companyOwner())
        ->from(route('admin.settings.policy.pdf'))
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()]);

    $response->assertRedirect(route('admin.settings.policy.pdf'));
    $errors = session('errors')?->getBag('default')->all() ?? [];

    expect(implode(' ', $errors))->not->toContain('SQLSTATE')
        ->and(implode(' ', $errors))->not->toContain('/var/www');
    Log::shouldHaveReceived('error')->once();
});

it('stores policy PDFs under content-derived names on the private disk', function () {
    $owner = Actors::companyOwner();

    $this->actingAs($owner)
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf('../../evil name.pdf')])
        ->assertRedirect(route('admin.settings.policy.pdf'));

    $path = Files::pathOf(PolicyPdf::sole()->general_work_policy_file_id);

    expect($path)->toStartWith('companies/'.$owner->company_id.'/policies/')
        ->and(basename($path))->toMatch('/^[A-Za-z0-9]{40}\.pdf$/');
    Storage::disk('local')->assertExists($path);
});

it('rejects a non-PDF policy upload', function () {
    $this->actingAs(Actors::companyOwner())
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::realUpload('policy.pdf', '<html><script>alert(1)</script></html>')])
        ->assertSessionHasErrors('general_work_policy_pdf');

    expect(PolicyPdf::count())->toBe(0);
});

it('files an owner\'s upload under their own company', function () {
    $owner = Actors::companyOwner();

    $this->actingAs($owner)
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()])
        ->assertRedirect(route('admin.settings.policy.pdf'));

    expect(PolicyPdf::sole()->company_id)->toBe($owner->company_id);
});

it('forbids a user without company.edit', function () {
    $this->actingAs(Actors::plainUser())
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()])
        ->assertForbidden();

    expect(PolicyPdf::count())->toBe(0);
});

it('replaces the old file when a new policy PDF is uploaded', function () {
    $owner = Actors::companyOwner();

    $this->actingAs($owner)->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()]);
    $first = Files::pathOf(PolicyPdf::sole()->general_work_policy_file_id);

    $this->actingAs($owner)->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()]);
    $second = Files::pathOf(PolicyPdf::sole()->general_work_policy_file_id);

    expect($second)->not->toBe($first)
        ->and(PolicyPdf::count())->toBe(1);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);
});

it('serves the company\'s policy to its staff, and 404 to another company', function () {
    $owner = Actors::companyOwner();
    $this->actingAs($owner)->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()]);

    $this->actingAs($owner)->get(route('admin.settings.policy.pdf.file', 'general_work'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->actingAs(Actors::companyOwner())->get(route('admin.settings.policy.pdf.file', 'general_work'))->assertNotFound();
    $this->actingAs($owner)->get(route('admin.settings.policy.pdf.file', 'unknown'))->assertNotFound();
});
