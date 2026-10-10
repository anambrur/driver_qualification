<?php

use App\Models\PolicyPdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Companies\Companies;
use Tests\Support\Actors;

/*
| CMP-05: the upload error path returned the raw exception message to the browser. Whether
| PolicyPdf should be per company instead of global (PolicyPdf::first()) is DEFERRED.
*/

beforeEach(function () {
    Storage::fake('public');
});

it('does not leak the exception message when the upload fails', function () {
    Log::spy();
    Storage::shouldReceive('disk')->andThrow(new RuntimeException('SQLSTATE secret /var/www/internal/path'));

    $response = $this->actingAs(Actors::superAdmin())
        ->from(route('admin.settings.policy.pdf'))
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()]);

    $response->assertRedirect(route('admin.settings.policy.pdf'));
    $errors = session('errors')?->getBag('default')->all() ?? [];

    expect(implode(' ', $errors))->not->toContain('SQLSTATE')
        ->and(implode(' ', $errors))->not->toContain('/var/www');
    Log::shouldHaveReceived('error')->once();
});

it('stores policy PDFs under content-derived names', function () {
    $this->actingAs(Actors::superAdmin())
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf('../../evil name.pdf')])
        ->assertRedirect(route('admin.settings.company'));

    $path = PolicyPdf::first()->general_work_policy_pdf;

    expect($path)->toStartWith('policy_pdfs/')
        ->and(basename($path))->toMatch('/^[A-Za-z0-9]{40}\.pdf$/');
    Storage::disk('public')->assertExists($path);
});

it('rejects a non-PDF policy upload', function () {
    $this->actingAs(Actors::superAdmin())
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::realUpload('policy.pdf', '<html><script>alert(1)</script></html>')])
        ->assertSessionHasErrors('general_work_policy_pdf');

    expect(PolicyPdf::count())->toBe(0);
});

it('does not let a company owner upload global policy PDFs', function () {
    $this->actingAs(Actors::companyOwner())
        ->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()])
        ->assertForbidden();

    expect(PolicyPdf::count())->toBe(0);
});

it('replaces the old file when a new policy PDF is uploaded', function () {
    $admin = Actors::superAdmin();

    $this->actingAs($admin)->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()]);
    $first = PolicyPdf::first()->general_work_policy_pdf;

    $this->actingAs($admin)->post(route('admin.settings.policy.pdf.store'), ['general_work_policy_pdf' => Companies::pdf()]);
    $second = PolicyPdf::first()->general_work_policy_pdf;

    expect($second)->not->toBe($first)
        ->and(PolicyPdf::count())->toBe(1);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});
