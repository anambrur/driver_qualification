<?php

use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;
use Tests\Support\Actors;

/*
| FCMP-02: vehicle and trailer documents were stored on the public disk as
| documents/<type>s/<time>_<client name>, so anyone could read them at /storage/... without
| logging in, bypassing the authorized view/download routes. The client name and extension
| were also kept, so PDF bytes uploaded as x.html were served as HTML. They are now stored on
| the private `local` disk under a random name and served only through the authorized routes.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

describe('FCMP-02: fleet compliance documents are private', function () {
    it('stores an uploaded document on the private disk', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $type = FleetCompliance::documentType($assetType);

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type), FleetCompliance::json())
            ->assertOk()->assertJson(['success' => true]);

        $path = FleetCompliance::documentModel($assetType)::where($assetType.'_id', $asset->id)->value('file_path');
        expect($path)->toStartWith('documents/'.$assetType.'s/');
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    })->with(['vehicle', 'trailer']);

    it('serves the file to the asset\'s tenant through the view and download routes', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));

        $this->actingAs($owner)
            ->get(route('admin.compliance.documents.view', [$document->id, $assetType]))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($owner)
            ->get(route('admin.compliance.documents.download', [$document->id, $assetType]))
            ->assertOk()->assertDownload();
    })->with(['vehicle', 'trailer']);

    it('lets a super-admin open any company\'s document', function (string $assetType) {
        [, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));

        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.compliance.documents.view', [$document->id, $assetType]))
            ->assertOk();
    })->with(['vehicle', 'trailer']);

    it('refuses another tenant', function (string $assetType, string $route) {
        [, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));

        $this->actingAs(Actors::companyOwner())
            ->get(route($route, [$document->id, $assetType]))
            ->assertForbidden();
    })->with(['vehicle', 'trailer'])->with(['admin.compliance.documents.view', 'admin.compliance.documents.download']);

    it('refuses guests and users without fleet permissions', function () {
        [, $asset] = FleetCompliance::tenantWithAsset();
        $document = FleetCompliance::document($asset, FleetCompliance::documentType());
        $url = route('admin.compliance.documents.view', [$document->id, 'vehicle']);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(Actors::plainUser())->get($url)->assertForbidden();
    });

    it('still serves documents uploaded before the move, from the public disk', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType), disk: 'public');

        $this->actingAs($owner)
            ->get(route('admin.compliance.documents.view', [$document->id, $assetType]))
            ->assertOk();
    })->with(['vehicle', 'trailer']);

    it('deletes a legacy file from the public disk when the document is replaced', function () {
        [$owner, $asset] = FleetCompliance::tenantWithAsset();
        $type = FleetCompliance::documentType();
        $old = FleetCompliance::document($asset, $type, disk: 'public');

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type), FleetCompliance::json())
            ->assertOk();

        Storage::disk('public')->assertMissing($old->file_path);
        Storage::disk('local')->assertExists($old->fresh()->file_path);
    });

    it('returns 404 when the file is missing from both disks', function () {
        [$owner, $asset] = FleetCompliance::tenantWithAsset();
        $document = FleetCompliance::document($asset, FleetCompliance::documentType());
        Storage::disk('local')->delete($document->file_path);

        $this->actingAs($owner)
            ->get(route('admin.compliance.documents.view', [$document->id, 'vehicle']))
            ->assertNotFound();
    });

    it('links the dashboard preview to the authorized view route, never to /storage', function () {
        [$owner] = FleetCompliance::tenantWithAsset();

        $html = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()->getContent();

        expect($html)->not->toContain('/storage/${doc.file_path}')
            ->and($html)->toContain('/admin/compliance/documents/${doc.id}/${assetType}/view')
            ->and($html)->toContain("const assetType = 'vehicle';")
            ->and($html)->toContain("const assetType = 'trailer';");
    });
});

describe('FCMP-02: stored names come from the content, not the client', function () {
    it('stores PDF bytes uploaded as x.html under a random .pdf name', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $type = FleetCompliance::documentType($assetType);
        $file = FleetCompliance::fileWith('x.html', "%PDF-1.4\n%%EOF\n", 'text/html');

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type, ['file' => $file]), FleetCompliance::json())
            ->assertOk();

        $path = FleetCompliance::documentModel($assetType)::where($assetType.'_id', $asset->id)->value('file_path');
        expect($path)->toEndWith('.pdf')->not->toContain('x.html')->not->toContain('x_html');
    })->with(['vehicle', 'trailer']);

    it('gives two uploads of the same client name different stored names', function () {
        [$owner, $a] = FleetCompliance::tenantWithAsset();
        $b = FleetCompliance::assetOf($owner);
        $type = FleetCompliance::documentType();

        foreach ([$a, $b] as $asset) {
            $this->actingAs($owner)
                ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type), FleetCompliance::json())
                ->assertOk();
        }

        $paths = FleetCompliance::documentModel('vehicle')::pluck('file_path');
        expect($paths->unique())->toHaveCount(2)
            ->and($paths->first())->not->toContain('insurance');
    });

    it('rejects files whose content is not jpg, png or pdf', function (string $name, string $bytes, string $mime) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset();
        $type = FleetCompliance::documentType();

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type, ['file' => FleetCompliance::fileWith($name, $bytes, $mime)]), FleetCompliance::json())
            ->assertStatus(422);

        expect(Storage::disk('local')->allFiles())->toBe([])
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with([
        'html' => ['x.html', '<html><script>alert(1)</script></html>', 'text/html'],
        'svg named .pdf' => ['x.pdf', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'application/pdf'],
        'php named .pdf' => ['x.pdf', '<?php echo 1;', 'application/pdf'],
    ]);
});
