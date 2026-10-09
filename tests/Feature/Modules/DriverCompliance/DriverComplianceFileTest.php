<?php

use App\Models\DriverComplianceDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Support\Actors;

/*
| DCMP-01: compliance documents were stored on the *public* disk as
| documents/drivers/<time>_<client name>, so anyone could read them at /storage/... without
| logging in, bypassing the authorized view/download routes. They now live on the private
| `local` disk under a random name and are only served by those routes.
| DCMP-04: the stored name kept the client's name and extension, so PDF bytes uploaded as
| x.html passed `mimes:pdf` and were served as HTML from /storage.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

describe('DCMP-01: compliance documents are private', function () {
    it('stores an uploaded document on the private disk', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($driver, $type), DriverCompliance::json())
            ->assertOk()->assertJson(['success' => true]);

        $path = DriverComplianceDocument::where('driver_id', $driver->id)->value('file_path');
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    });

    it('serves the file to the driver\'s tenant through the view and download routes', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType(['name' => 'Medical Card']));

        $this->actingAs($owner)->get(route('admin.compliance.driver.documents.view', $document->id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($owner)->get(route('admin.compliance.driver.documents.download', $document->id))
            ->assertOk()
            ->assertDownload('Medical Card_'.basename($document->file_path));
    });

    it('lets a super-admin open any company\'s document', function () {
        [, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType());

        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.compliance.driver.documents.view', $document->id))
            ->assertOk();
    });

    it('refuses another tenant', function (string $route) {
        [, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType());

        $this->actingAs(Actors::companyOwner())->get(route($route, $document->id))->assertForbidden();
    })->with(['admin.compliance.driver.documents.view', 'admin.compliance.driver.documents.download']);

    it('refuses guests and users without driver permissions', function () {
        [, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType());

        $this->get(route('admin.compliance.driver.documents.view', $document->id))->assertRedirect(route('login'));
        $this->actingAs(Actors::plainUser())
            ->get(route('admin.compliance.driver.documents.view', $document->id))
            ->assertForbidden();
    });

    it('still serves documents uploaded before the move, from the public disk', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType(), disk: 'public');

        $this->actingAs($owner)->get(route('admin.compliance.driver.documents.view', $document->id))->assertOk();
        $this->actingAs($owner)->get(route('admin.compliance.driver.documents.download', $document->id))->assertOk();
    });

    it('deletes a legacy file from the public disk when the document is replaced', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();
        $old = DriverCompliance::document($driver, $type, disk: 'public');

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($driver, $type), DriverCompliance::json())
            ->assertOk();

        Storage::disk('public')->assertMissing($old->file_path);
        Storage::disk('local')->assertExists($old->fresh()->file_path);
    });

    it('returns 404 when the file is missing from both disks', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType());
        Storage::disk('local')->delete($document->file_path);

        $this->actingAs($owner)->get(route('admin.compliance.driver.documents.view', $document->id))->assertNotFound();
    });

    it('links the dashboard preview to the authorized view route, never to /storage', function () {
        [$owner] = DriverCompliance::tenantWithDriver();

        $html = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()->getContent();

        expect($html)->not->toContain('/storage/${doc.file_path}')
            ->and($html)->toContain('/admin/compliance/driver-documents/${doc.id}/view');
    });

    it('moves files already on the public disk to the private disk, and can move them back', function () {
        [, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();
        $moved = DriverCompliance::document($driver, $type, disk: 'public');
        DriverCompliance::document(DriverCompliance::tenantWithDriver()[1], $type, ['file_path' => 'documents/drivers/missing.pdf']);
        Storage::disk('local')->delete('documents/drivers/missing.pdf');
        $content = Storage::disk('public')->get($moved->file_path);

        $migration = require database_path('migrations/2026_10_09_000900_move_driver_compliance_files_to_private_disk.php');
        $migration->up();

        Storage::disk('public')->assertMissing($moved->file_path);
        expect(Storage::disk('local')->get($moved->file_path))->toBe($content);

        $migration->down();

        Storage::disk('local')->assertMissing($moved->file_path);
        expect(Storage::disk('public')->get($moved->file_path))->toBe($content);
    });
});

describe('DCMP-04: stored names come from the content, not the client', function () {
    it('stores PDF bytes uploaded as x.html under a random .pdf name', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();
        $file = DriverCompliance::pdf('x.html', '<script>alert(document.cookie)</script>');

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($driver, $type, ['file' => $file]), DriverCompliance::json())
            ->assertOk();

        $path = DriverComplianceDocument::where('driver_id', $driver->id)->value('file_path');
        expect($path)->toStartWith('documents/drivers/')
            ->toEndWith('.pdf')
            ->not->toContain('x.html')
            ->not->toContain('x_html');
    });

    it('gives two uploads of the same client name different stored names', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $other = DriverCompliance::driverOf($owner);
        $type = DriverCompliance::documentType();

        foreach ([$driver, $other] as $d) {
            $this->actingAs($owner)
                ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($d, $type), DriverCompliance::json())
                ->assertOk();
        }

        expect(DriverComplianceDocument::pluck('file_path')->unique())->toHaveCount(2);
    });

    it('rejects files whose content is not jpg, png or pdf', function (string $name, string $bytes, string $mime) {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs($owner)
            ->post(
                route('admin.compliance.driver.documents.upload'),
                DriverCompliance::payload($driver, $type, ['file' => DriverCompliance::fileWith($name, $bytes, $mime)]),
                DriverCompliance::json()
            )
            ->assertStatus(422);

        expect(DriverComplianceDocument::count())->toBe(0);
    })->with([
        'html' => ['x.html', '<html><body><script>alert(1)</script></body></html>', 'text/html'],
        'svg named .pdf' => ['x.pdf', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'application/pdf'],
        'php named .pdf' => ['x.pdf', '<?php echo 1;', 'application/pdf'],
    ]);
});
