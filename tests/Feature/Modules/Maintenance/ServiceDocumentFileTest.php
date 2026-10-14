<?php

use App\Models\ServiceDocument;
use App\Models\ServiceLog;
use App\Models\Vehicle;
use App\Tenancy\TenantContext;
use Database\Factories\ServiceLogFactory;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Support\Actors;

/*
| MNT-05: service documents (invoices, receipts) were stored on the public disk, readable at
| /storage/service-documents/... without logging in, under a name ending in the client's
| extension, so PDF bytes uploaded as x.html were saved and served as HTML. They now go to the
| private disk under a random name with the extension of their content, and are only served
| through admin.service-log.download-document.
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function mntLogWithDocument(Vehicle $vehicle, string $disk = 'local'): ServiceDocument
{
    $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();
    $path = 'service-documents/'.$log->id.'/abc.pdf';
    Storage::disk($disk)->put($path, '%PDF-1.4 test');

    return app(TenantContext::class)->run($log->company_id, fn () => ServiceDocument::create([
        'service_log_id' => $log->id, 'original_name' => 'invoice.pdf', 'file_name' => 'abc.pdf',
        'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 13,
    ]));
}

describe('MNT-05: storage', function () {
    it('stores an uploaded document on the private disk under a random name', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)
            ->post(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['documents' => [Maintenance::pdf('invoice.pdf')]]), ['Accept' => 'application/json'])
            ->assertOk();

        $document = ServiceDocument::withoutTenancy()->sole();
        expect($document->file_path)->toStartWith('service-documents/'.ServiceLog::withoutTenancy()->sole()->id.'/')
            ->and($document->file_path)->toEndWith('.pdf')
            ->and(basename($document->file_path))->not->toContain('invoice')
            ->and($document->original_name)->toBe('invoice.pdf');
        Storage::disk('local')->assertExists($document->file_path);
        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('stores documents added on edit on the private disk too', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();

        $this->actingAs($owner)
            ->post(route('admin.service-log.update', $log->id), Maintenance::serviceLogPayload($vehicle, ['_method' => 'PUT', 'new_documents' => [Maintenance::pdf()]]), ['Accept' => 'application/json'])
            ->assertOk();

        Storage::disk('local')->assertExists(ServiceDocument::withoutTenancy()->sole()->file_path);
        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('stores PDF bytes uploaded as x.html under a .pdf name', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)
            ->post(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['documents' => [Maintenance::pdf('x.html')]]), ['Accept' => 'application/json'])
            ->assertOk();

        expect(ServiceDocument::withoutTenancy()->sole()->file_path)->toEndWith('.pdf');
    });

    it('rejects files whose content is not an allowed type', function (string $name, string $bytes, string $mime) {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)
            ->post(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['documents' => [Maintenance::fileWith($name, $bytes, $mime)]]), ['Accept' => 'application/json'])
            ->assertUnprocessable();

        expect(ServiceDocument::withoutTenancy()->count())->toBe(0);
    })->with([
        'html' => ['x.html', '<html><script>alert(1)</script></html>', 'text/html'],
        'svg named .pdf' => ['x.pdf', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'application/pdf'],
        'php named .pdf' => ['x.pdf', '<?php echo 1; ?>', 'application/pdf'],
    ]);

    it('never links a document to /storage', function () {
        [, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle);

        expect($document->file_url)->toBe(route('admin.service-log.download-document', $document->id));
    });
});

describe('MNT-05: download', function () {
    it('serves the file to the log\'s tenant', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle);

        $response = $this->actingAs($owner)->get(route('admin.service-log.download-document', $document->id))->assertOk();

        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
            ->and($response->headers->get('Content-Disposition'))->toContain('invoice.pdf');
    });

    it('still serves documents uploaded before the move, from the public disk', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle, 'public');

        $this->actingAs($owner)->get(route('admin.service-log.download-document', $document->id))->assertOk();
    });

    it('lets a super-admin download any company\'s document', function () {
        [, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle);

        $this->actingAs(Actors::superAdmin())->get(route('admin.service-log.download-document', $document->id))->assertOk();
    });

    it('returns 404 to another tenant', function () {
        [$owner] = Maintenance::tenantWithVehicle();
        [, $foreign] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($foreign);

        $this->actingAs($owner)->get(route('admin.service-log.download-document', $document->id))->assertNotFound();
        $this->actingAs($owner)->deleteJson(route('admin.service-log.delete-document', $document->id))->assertNotFound();

        Storage::disk('local')->assertExists($document->file_path);
    });

    it('returns 404 when the file is missing from both disks', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle);
        Storage::disk('local')->delete($document->file_path);

        $this->actingAs($owner)->get(route('admin.service-log.download-document', $document->id))->assertNotFound();
    });

    it('returns 404, not a 500, for a document of a deleted log', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle);
        $document->serviceLog->delete();

        $this->actingAs($owner)->get(route('admin.service-log.download-document', $document->id))->assertNotFound();
        $this->actingAs($owner)->deleteJson(route('admin.service-log.delete-document', $document->id))->assertNotFound();
    });
});

describe('MNT-05: delete', function () {
    it('deletes the file from the private disk with the document', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle);

        $this->actingAs($owner)->deleteJson(route('admin.service-log.delete-document', $document->id))->assertOk();

        Storage::disk('local')->assertMissing($document->file_path);
        expect(ServiceDocument::withoutTenancy()->count())->toBe(0);
    });

    it('deletes legacy files from the public disk too', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle, 'public');

        $this->actingAs($owner)->deleteJson(route('admin.service-log.destroy', $document->service_log_id))->assertOk();

        Storage::disk('public')->assertMissing($document->file_path);
    });

    it('deletes the files removed on edit', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $document = mntLogWithDocument($vehicle);

        $this->actingAs($owner)
            ->putJson(route('admin.service-log.update', $document->service_log_id), Maintenance::serviceLogPayload($vehicle, ['delete_documents' => [$document->id]]))
            ->assertOk();

        Storage::disk('local')->assertMissing($document->file_path);
    });
});
