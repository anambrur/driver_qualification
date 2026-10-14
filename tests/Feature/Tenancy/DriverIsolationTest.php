<?php

use App\Http\Middleware\ResolvePublicCompany;
use App\Mail\DriverComplianceDigestMail;
use App\Mail\DriverHiredMail;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Models\DriverDocument;
use App\Models\StoredFile;
use App\Models\Violation;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverComplianceDocumentFactory;
use Database\Factories\DriverFactory;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| WP5 group 1: drivers, their compliance documents and stored files carry the tenant scope
| (03 §4.2). Another company's driver or document is a 404 by URL and by request body.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->companyA = Actors::company();
    $this->companyB = Actors::company();
    $this->driverA = DriverFactory::new()->forCompany($this->companyA)->create();
    $this->driverB = DriverFactory::new()->forCompany($this->companyB)->create();
    $this->ownerB = $this->companyB->owner;
});

describe('scope', function () {
    it('shows each company only its own drivers, documents and files', function () {
        $type = DocumentTypeFactory::new()->module('driver')->create();
        DriverComplianceDocumentFactory::new()->forDriver($this->driverA, $type)->create();
        DriverComplianceDocumentFactory::new()->forDriver($this->driverB, $type)->create();

        $seen = app(TenantContext::class)->run($this->companyB->id, fn () => [
            Driver::pluck('id')->all(),
            DriverComplianceDocument::pluck('driver_id')->all(),
            StoredFile::pluck('company_id')->unique()->values()->all(),
        ]);

        expect($seen)->toBe([[$this->driverB->id], [$this->driverB->id], [$this->companyB->id]]);
    });

    it('fills company_id from the context on create', function () {
        $driver = app(TenantContext::class)->run($this->companyB->id, fn () => Driver::create([
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'main_phone' => '+12025550123',
            'email' => 'ada@example.test', 'source' => 'admin',
        ]));

        expect($driver->company_id)->toBe($this->companyB->id);
    });
});

describe('another company\'s driver by URL', function () {
    it('is a 404', function (string $method, string $route, Closure $params) {
        $this->actingAs($this->ownerB)
            ->json($method, route($route, $params($this->driverA)))
            ->assertNotFound();
    })->with([
        'show' => ['GET', 'admin.driver.show', fn (Driver $d) => $d->id],
        'edit' => ['GET', 'admin.driver.edit', fn (Driver $d) => $d->id],
        'update (before validation)' => ['PUT', 'admin.driver.update', fn (Driver $d) => $d->id],
        'destroy' => ['DELETE', 'admin.driver.destroy', fn (Driver $d) => $d->id],
        'contact details' => ['GET', 'admin.drivers.get-driver-details', fn (Driver $d) => $d->id],
        'file' => ['GET', 'admin.driver.file', fn (Driver $d) => [$d->id, 'license_front']],
        'wizard step' => ['GET', 'admin.driver.license', fn (Driver $d) => $d->id],
        'hire status' => ['POST', 'admin.driver.hire-status', fn (Driver $d) => $d->id],
        'compliance details' => ['GET', 'admin.compliance.driver.details', fn (Driver $d) => $d->id],
    ]);

    it('keeps the driver when the delete is refused', function () {
        $this->actingAs($this->ownerB)->deleteJson(route('admin.driver.destroy', $this->driverA->id))->assertNotFound();

        expect(Driver::withoutTenancy()->find($this->driverA->id))->not->toBeNull();
    });
});

describe('another company\'s driver in the request body', function () {
    it('stores no compliance document for it', function () {
        $type = DocumentTypeFactory::new()->module('driver')->create();

        $this->actingAs($this->ownerB)
            ->postJson(route('admin.compliance.driver.documents.upload'), [
                'document_type_id' => $type->id,
                'asset_type' => 'driver',
                'selected_asset' => $this->driverA->id,
                'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();

        expect(DriverComplianceDocument::withoutTenancy()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('sends no compliance reminder for it', function () {
        Mail::fake();
        $type = DocumentTypeFactory::new()->module('driver')->create();

        $this->actingAs($this->ownerB)
            ->postJson(route('admin.compliance.driver.documents.send-reminder'), [
                'driver_id' => $this->driverA->id,
                'document_type_id' => $type->id,
            ])
            ->assertUnprocessable();

        Mail::assertNothingOutgoing();
    });

    it('saves nothing for it in a wizard step', function () {
        $this->actingAs($this->ownerB)
            ->post(route('admin.driver.violation.store'), [
                'driver_id' => $this->driverA->id,
                'violation' => 'no',
                'applicant_signature' => 'B Owner',
                'date_signed' => now()->toDateString(),
            ])
            ->assertRedirect();

        expect(Violation::where('driver_id', $this->driverA->id)->exists())->toBeFalse()
            ->and(DriverDocument::where('driver_id', $this->driverA->id)->exists())->toBeFalse();
    });
});

describe('another company\'s compliance document', function () {
    it('is a 404 to view, download and delete', function () {
        $document = DriverComplianceDocumentFactory::new()
            ->forDriver($this->driverA, DocumentTypeFactory::new()->module('driver')->create())
            ->create();

        $this->actingAs($this->ownerB)->get(route('admin.compliance.driver.documents.view', $document->id))->assertNotFound();
        $this->actingAs($this->ownerB)->get(route('admin.compliance.driver.documents.download', $document->id))->assertNotFound();
        $this->actingAs($this->ownerB)
            ->deleteJson(route('admin.compliance.driver.documents.delete'), ['document_id' => $document->id])
            ->assertNotFound();

        expect(DriverComplianceDocument::withoutTenancy()->find($document->id))->not->toBeNull();
    });
});

describe('entry points without a logged-in tenant', function () {
    it('runs the public application as the slug\'s company', function () {
        Route::middleware(['web', ResolvePublicCompany::class])
            ->get('/{slug}/_tenancy-public', fn () => app(TenantContext::class)->mode().':'.Driver::pluck('id')->implode(','));

        $this->get("/{$this->companyA->slug}/_tenancy-public")->assertSeeText("public_applicant:{$this->driverA->id}");
        $this->get('/no-such-company/_tenancy-public')->assertNotFound();

        Actors::suspended($this->companyA);
        $this->get("/{$this->companyA->slug}/_tenancy-public")->assertNotFound();
    });

    it('sends a queued driver mail from a worker without a context', function () {
        $type = DocumentTypeFactory::new()->module('driver')->create();
        DriverComplianceDocumentFactory::new()->forDriver($this->driverA, $type)->create();
        $driver = app(TenantContext::class)->run($this->companyA->id, fn () => Driver::with('documents')->find($this->driverA->id));

        $job = unserialize(serialize(new SendQueuedMailable((new DriverHiredMail($driver, 'Acme'))->to($driver->email))));
        $job->handle(app(MailFactory::class));

        expect(app(TenantContext::class)->companyId())->toBeNull();
    });

    it('sends the compliance digest to every company\'s drivers, each in its own context', function () {
        Mail::fake();
        DocumentTypeFactory::new()->module('driver')->create(['status' => true]);

        $this->artisan('compliance:send-digest-reminders')->assertSuccessful();

        Mail::assertQueued(DriverComplianceDigestMail::class, 2);
        expect(app(TenantContext::class)->companyId())->toBeNull();
    });
});
