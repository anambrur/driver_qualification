<?php

use App\Features\Feature;
use App\Models\Document;
use App\Models\DriverComplianceDocument;
use App\Models\Folder;
use App\Support\Navigation\CompanyMenu;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Database\Factories\FolderFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Support\Actors;
use Tests\Support\Files;
use Tests\Support\Perf;

/*
| Folders and documents access (06, 05 §4): route permissions, the entity's view permission for
| bound folders, the read-only access state, the document_folders feature, the pages and the
| query budget (12 §5).
*/

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Every write route of the package, with what it needs.
 *
 * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
 */
function documentWriteRoutes(Folder $folder, Document $document, Folder $target): array
{
    return [
        'create folder' => ['post', route('documents.folders.store'), ['parent_id' => $folder->id, 'name' => 'New']],
        'rename folder' => ['put', route('documents.folders.update', $target), ['name' => 'Renamed']],
        'delete folder' => ['delete', route('documents.folders.destroy', $target), []],
        'upload' => ['post', route('documents.store', $folder), ['files' => [Docs::pdf()]]],
        'move' => ['patch', route('documents.move', $document), ['folder_id' => $target->id]],
        'delete document' => ['delete', route('documents.destroy', $document), []],
    ];
}

describe('permissions', function () {
    it('needs the entity view permission to open a bound folder', function () {
        $company = Actors::company();
        [, $dqFile] = Docs::dqFile($company);
        $driverDocument = DocumentFactory::new()->inFolder($dqFile)->withFile(Files::stored($company->id))->create();
        $companyFolder = Docs::companyFolder($company);
        $user = Actors::memberWithPermissions($company, ['folders.view']);

        $this->actingAs($user)->getJson(route('documents.folders.documents', $dqFile))->assertForbidden();
        $this->get(route('documents.download', $driverDocument))->assertForbidden();
        $this->getJson(route('documents.folders.documents', $companyFolder))->assertOk();
        $this->get(route('documents.index'))->assertOk();
    });

    it('refuses folder changes and uploads without the permission', function () {
        $company = Actors::company();
        $folder = Docs::companyFolder($company);
        $target = Docs::childOf($folder);
        $document = DocumentFactory::new()->inFolder($folder)->create();
        $user = Actors::memberWithPermissions($company, ['folders.view']);

        foreach (documentWriteRoutes($folder, $document, $target) as $name => [$method, $url, $data]) {
            expect($this->actingAs($user)->{$method}($url, $data)->status())->toBe(403, $name);
        }
    });

    it('refuses every write route for a read-only company, but keeps the pages', function () {
        $company = Actors::company();
        $folder = Docs::companyFolder($company);
        $target = Docs::childOf($folder);
        $document = DocumentFactory::new()->inFolder($folder)->create();
        $owner = Actors::readOnly($company)->owner;

        foreach (documentWriteRoutes($folder, $document, $target) as $name => [$method, $url, $data]) {
            expect($this->actingAs($owner)->{$method}($url, $data)->status())->toBe(403, $name);
        }

        $this->get(route('documents.index'))->assertOk();
        expect(Folder::withoutTenancy()->count())->toBe(2)
            ->and($document->fresh()->trashed())->toBeFalse()
            ->and($target->fresh()->name)->not->toBe('Renamed');
    });

    it('hides the menu, the tab and the routes without the document_folders feature', function () {
        $company = Actors::company();
        $folder = Docs::companyFolder($company);
        $driver = DriverFactory::new()->forCompany($company)->create();
        Actors::withPlan($company, collect(Feature::cases())->reject(fn (Feature $f) => $f === Feature::DocumentFolders || $f->isLimit())->map->value->all());
        $owner = $company->fresh()->owner;

        $this->actingAs($owner)->get(route('documents.index'))->assertForbidden();
        $this->getJson(route('documents.folders.documents', $folder))->assertForbidden();
        $this->post(route('documents.folders.store'), ['name' => 'X'])->assertForbidden();
        $this->get(route('admin.driver.show', $driver->id))->assertOk()->assertDontSee('driver-tabs-tab-documents', false);

        Actors::actingAsTenant($owner);
        $labels = collect(CompanyMenu::for($owner, new Request))->flatMap(fn ($group) => collect($group['items'])->pluck('label'));
        expect($labels)->not->toContain('Documents');
    });

    it('shows the Documents menu item with the feature and folders.view', function () {
        $owner = Actors::actingAsTenant(Actors::companyOwner());

        $labels = collect(CompanyMenu::for($owner, new Request))->flatMap(fn ($group) => collect($group['items'])->pluck('label'));
        expect($labels)->toContain('Documents');
    });
});

describe('isolation of ids in the body', function () {
    it('404s a parent folder or a move target of another company', function () {
        $owner = Actors::companyOwner();
        $mine = DocumentFactory::new()->inFolder(Docs::companyFolder(Actors::companyOf($owner)))->create();
        $foreign = Docs::companyFolder(Actors::company());

        $this->actingAs($owner)->post(route('documents.folders.store'), ['parent_id' => $foreign->id, 'name' => 'Leak'])->assertNotFound();
        $this->patch(route('documents.move', $mine), ['folder_id' => $foreign->id])->assertSessionHasErrors('folder_id');

        expect(Folder::withoutTenancy()->where('name', 'Leak')->exists())->toBeFalse()
            ->and($mine->fresh()->folder_id)->not->toBe($foreign->id);
    });
});

describe('pages', function () {
    it('shows the company folders, the open folder and its breadcrumb', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $insurance = Docs::companyFolder($company, ['name' => 'Insurance']);
        $claims = Docs::childOf($insurance, ['name' => 'Claims']);
        Docs::dqFile($company);

        $this->actingAs($owner)->get(route('documents.index', ['folder' => $claims->id]))
            ->assertOk()
            ->assertSeeInOrder(['Insurance', 'Claims'])
            ->assertSee(route('documents.folders.documents', $claims), false)
            ->assertDontSee('DQ file');
    });

    it('opens a bound folder only on its entity page', function () {
        $owner = Actors::companyOwner();
        [, $dqFile] = Docs::dqFile(Actors::companyOf($owner));

        $this->actingAs($owner)->get(route('documents.index', ['folder' => $dqFile->id]))->assertNotFound();
    });

    it('shows the DQ file with its compliance documents read-only on the driver page', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        [$driver] = Docs::dqFile($company);
        $type = DocumentTypeFactory::new()->module('driver')->create(['name' => 'Medical examiner certificate']);
        $compliance = app(TenantContext::class)->run($company->id, fn () => DriverComplianceDocument::query()->create([
            'driver_id' => $driver->id, 'document_type_id' => $type->id,
            'file_id' => StoredFileFactory::new()->forCompany($company)->create()->id, 'expiry_date' => now()->addYear(),
        ]));

        $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))
            ->assertOk()
            ->assertSee('driver-tabs-tab-documents', false)
            ->assertSee('Compliance documents')
            ->assertSee('Medical examiner certificate')
            ->assertSee(route('admin.compliance.driver.documents.view', $compliance->id), false);
    });

    it('tells that a public applicant gets the DQ file at hire', function () {
        $owner = Actors::companyOwner();
        $applicant = DriverFactory::new()->forCompany(Actors::companyOf($owner))->application('submitted')->create();

        $this->actingAs($owner)->get(route('admin.driver.show', $applicant->id))
            ->assertOk()->assertSee('The DQ file is created when the driver is hired.');
    });

    it('adds a Documents tab to the vehicle and trailer pages', function (string $kind) {
        $owner = Actors::companyOwner();
        $factory = $kind === 'vehicle' ? VehicleFactory::new() : TrailerFactory::new();
        $asset = $factory->forCompany(Actors::companyOf($owner))->create();
        $folder = Docs::childOf(Docs::systemFolder($asset), ['name' => 'Permits']);

        $this->actingAs($owner)->get(route("admin.{$kind}.show", [$asset->id, 'folder' => $folder->id]))
            ->assertOk()
            ->assertSee('asset-tabs-tab-documents', false)
            ->assertSeeInOrder([$kind === 'vehicle' ? 'Vehicle documents' : 'Trailer documents', 'Permits']);
    })->with(['vehicle', 'trailer']);

    it('404s a folder of another entity on an entity page', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        [$driver] = Docs::dqFile($company);
        [, $otherDqFile] = Docs::dqFile($company);

        $this->actingAs($owner)->get(route('admin.driver.show', [$driver->id, 'folder' => $otherDqFile->id]))->assertNotFound();
    });
});

describe('query budget', function () {
    it('keeps the company page constant in the number of folders', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $root = Docs::companyFolder($company, ['name' => 'Root']);
        $this->actingAs($owner);

        Perf::assertConstantQueries(
            fn (int $n) => FolderFactory::new()->childOf($root)->count($n)->create(),
            fn () => $this->get(route('documents.index', ['folder' => $root->id]))->assertOk(),
            small: 2, large: 25,
        );
    });

    it('keeps the driver Documents tab constant in the number of folders', function () {
        $owner = Actors::companyOwner();
        [$driver, $dqFile] = Docs::dqFile(Actors::companyOf($owner));
        $this->actingAs($owner);

        Perf::assertConstantQueries(
            fn (int $n) => FolderFactory::new()->childOf($dqFile)->count($n)->create(),
            fn () => $this->get(route('admin.driver.show', $driver->id))->assertOk(),
            small: 2, large: 25,
        );
    });

    it('keeps the document list constant in the number of documents', function () {
        $owner = Actors::companyOwner();
        [, $dqFile] = Docs::dqFile(Actors::companyOf($owner));
        $this->actingAs($owner);

        Perf::assertConstantQueries(
            fn (int $n) => DocumentFactory::new()->inFolder($dqFile)->count($n)->create(),
            fn () => $this->getJson(route('documents.folders.documents', $dqFile).'?draw=1&start=0&length=50')->assertOk(),
            small: 2, large: 25,
        );
    });
});

it('needs the folder entity permission for a document about a service log', function () {
    $company = Actors::company();
    $vehicle = VehicleFactory::new()->forCompany($company)->create();
    $document = DocumentFactory::new()->inFolder(Docs::systemFolder($vehicle))->withFile(Files::stored($company->id))->create([
        'vehicle_id' => null, 'service_log_id' => ServiceLogFactory::new()->forVehicle($vehicle)->create()->id,
    ]);

    $this->actingAs(Actors::memberWithPermissions($company, ['folders.view']))->get(route('documents.download', $document))->assertForbidden();
    $this->actingAs(Actors::memberWithPermissions($company, ['folders.view', 'vehicles.view']))->get(route('documents.download', $document))->assertOk();
});
