<?php

use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;
use Tests\Support\Actors;
use Tests\Support\Files;

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

        $path = Files::pathOf(FleetCompliance::documentModel($assetType)::withoutTenancy()->where($assetType.'_id', $asset->id)->value('file_id'));
        expect($path)->toStartWith('companies/');
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

    it('lets platform support open a company\'s document inside a support session, and only that company\'s', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));
        [, $otherAsset] = FleetCompliance::tenantWithAsset($assetType);
        $other = FleetCompliance::document($otherAsset, FleetCompliance::documentType($assetType));

        Actors::inSupportSession(Actors::companyOf($owner), 'read');

        $this->get(route('admin.compliance.documents.view', [$document->id, $assetType]))->assertOk();
        $this->get(route('admin.compliance.documents.view', [$other->id, $assetType]))->assertNotFound();
    })->with(['vehicle', 'trailer']);

    it('refuses another tenant', function (string $assetType, string $route) {
        [, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));

        // Another company's id is not found (11 §5 step 3), not forbidden
        $this->actingAs(Actors::companyOwner())
            ->get(route($route, [$document->id, $assetType]))
            ->assertNotFound();
    })->with(['vehicle', 'trailer'])->with(['admin.compliance.documents.view', 'admin.compliance.documents.download']);

    it('refuses guests and users without fleet permissions', function () {
        [, $asset] = FleetCompliance::tenantWithAsset();
        $document = FleetCompliance::document($asset, FleetCompliance::documentType());
        $url = route('admin.compliance.documents.view', [$document->id, 'vehicle']);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(Actors::plainUser())->get($url)->assertForbidden();
    });

    it('serves a document from the disk its stored file is on', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType), disk: 'public');

        $this->actingAs($owner)
            ->get(route('admin.compliance.documents.view', [$document->id, $assetType]))
            ->assertOk();
    })->with(['vehicle', 'trailer']);

    it('purges a replaced file from the disk it is on', function () {
        [$owner, $asset] = FleetCompliance::tenantWithAsset();
        $type = FleetCompliance::documentType();
        $old = FleetCompliance::document($asset, $type, disk: 'public');

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type), FleetCompliance::json())
            ->assertOk();

        Storage::disk('public')->assertMissing(Files::pathOf($old->file_id));
        Storage::disk('local')->assertExists(Files::pathOf($old->fresh()->file_id));
    });

    it('returns 404 when the file is missing from both disks', function () {
        [$owner, $asset] = FleetCompliance::tenantWithAsset();
        $document = FleetCompliance::document($asset, FleetCompliance::documentType());
        Storage::disk('local')->delete(Files::pathOf($document->file_id));

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

        $path = Files::pathOf(FleetCompliance::documentModel($assetType)::withoutTenancy()->where($assetType.'_id', $asset->id)->value('file_id'));
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

        $paths = FleetCompliance::documentModel('vehicle')::withoutTenancy()->pluck('file_id')->map(fn ($id) => Files::pathOf($id));
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
