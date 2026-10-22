<?php

use App\Models\AuditLog;
use App\Models\Folder;
use App\Services\Documents\FolderBrowser;
use Database\Factories\DocumentFactory;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Support\Actors;

/*
| Req 2.1–2.2 (task 1.2): standard folders per entity, custom folders nested up to 5 levels that
| take their parent's binding, unique sibling names, standard folders locked (08 §8).
*/

beforeEach(function () {
    $this->owner = Actors::companyOwner();
    $this->company = Actors::companyOf($this->owner);
});

function newFolder($test, ?Folder $parent, string $name)
{
    return $test->actingAs($test->owner)->post(route('documents.folders.store'), ['parent_id' => $parent?->id, 'name' => $name, '_dialog' => 'folder-new']);
}

it('nests custom folders inside a DQ file, each with the driver as binding (acceptance)', function () {
    [$driver, $dqFile] = Docs::dqFile($this->company);

    newFolder($this, $dqFile, 'Medical')->assertRedirect();
    $medical = Folder::withoutTenancy()->where('name', 'Medical')->sole();
    newFolder($this, $medical, '2026')
        ->assertRedirect(route('admin.driver.show', [$driver->id, 'folder' => Folder::withoutTenancy()->max('id')]).'#documents');
    $year = Folder::withoutTenancy()->where('name', '2026')->sole();

    expect([$medical->parent_id, $year->parent_id])->toBe([$dqFile->id, $medical->id])
        ->and([$medical->driver_id, $year->driver_id])->toBe([$driver->id, $driver->id])
        ->and([$medical->system_key, $year->system_key])->toBe([null, null])
        ->and($year->created_by_user_id)->toBe($this->owner->id);
});

it('creates company-level folders without a binding, and their children too', function () {
    newFolder($this, null, 'Insurance')->assertRedirect();
    $insurance = Folder::withoutTenancy()->where('name', 'Insurance')->sole();
    newFolder($this, $insurance, 'Claims')->assertRedirect(route('documents.index', ['folder' => $insurance->id + 1]));

    expect(Folder::withoutTenancy()->where('company_id', $this->company->id)->whereNotNull('parent_id')->sole()->binding())->toBeNull()
        ->and($insurance->binding())->toBeNull();
});

it('refuses a sixth level', function () {
    [, $level] = Docs::dqFile($this->company);
    foreach ([2, 3, 4, 5] as $depth) {
        $level = Docs::childOf($level, ['name' => "Level {$depth}"]);
    }

    newFolder($this, $level, 'Level 6')->assertSessionHasErrors(['name' => 'Folders can be nested 5 levels deep.']);

    expect(Folder::withoutTenancy()->where('name', 'Level 6')->exists())->toBeFalse();
});

it('keeps sibling names unique, ignoring case, but allows the name under another parent', function () {
    [, $dqFile] = Docs::dqFile($this->company);
    $medical = Docs::childOf($dqFile, ['name' => 'Medical']);

    newFolder($this, $dqFile, 'medical')->assertSessionHasErrors(['name' => 'A folder with this name already exists here.']);
    newFolder($this, $medical, 'Medical')->assertSessionHasNoErrors();

    $other = Docs::childOf($dqFile, ['name' => 'Other']);
    $this->put(route('documents.folders.update', $other), ['name' => 'MEDICAL'])->assertSessionHasErrors('name');
    expect($other->fresh()->name)->toBe('Other');
});

it('frees the name of a deleted folder', function () {
    $old = Docs::companyFolder($this->company, ['name' => 'Archive']);

    $this->actingAs($this->owner)->delete(route('documents.folders.destroy', $old))->assertRedirect(route('documents.index'));
    newFolder($this, null, 'Archive')->assertSessionHasNoErrors();

    expect($old->fresh()->trashed())->toBeTrue()
        ->and(Folder::withoutTenancy()->where('name', 'Archive')->count())->toBe(1);
});

it('renames a custom folder', function () {
    $folder = Docs::companyFolder($this->company, ['name' => 'Misc']);

    $this->actingAs($this->owner)->put(route('documents.folders.update', $folder), ['name' => '  Permits  '])
        ->assertRedirect(FolderBrowser::url($folder));

    expect($folder->fresh()->name)->toBe('Permits');
});

it('refuses a name with a slash', function () {
    newFolder($this, null, 'Q1/Q2')->assertSessionHasErrors('name');
});

it('locks the standard folder: no rename, no delete', function () {
    [$driver, $dqFile] = Docs::dqFile($this->company);

    $this->actingAs($this->owner)->put(route('documents.folders.update', $dqFile), ['name' => 'Renamed'])
        ->assertRedirect(route('admin.driver.show', [$driver->id, 'folder' => $dqFile->id]).'#documents')
        ->assertSessionHasErrors('folder');
    $this->delete(route('documents.folders.destroy', $dqFile))->assertSessionHasErrors('folder');

    expect($dqFile->fresh())->name->toBe("DQ file #{$driver->id}")->trashed()->toBeFalse();
});

it('deletes only an empty folder, and audits it', function () {
    $folder = Docs::companyFolder($this->company);
    $child = Docs::childOf($folder);
    $withDocument = Docs::companyFolder($this->company);
    DocumentFactory::new()->inFolder($withDocument)->create();

    $this->actingAs($this->owner)->delete(route('documents.folders.destroy', $folder))->assertSessionHasErrors('folder');
    $this->delete(route('documents.folders.destroy', $withDocument))->assertSessionHasErrors('folder');
    $this->delete(route('documents.folders.destroy', $child))->assertSessionHasNoErrors();
    $this->delete(route('documents.folders.destroy', $folder))->assertSessionHasNoErrors();

    expect($folder->fresh()->trashed())->toBeTrue()
        ->and($withDocument->fresh()->trashed())->toBeFalse()
        ->and(AuditLog::where('action', 'folder.deleted')->where('subject_id', $folder->id)->where('actor_id', $this->owner->id)->exists())->toBeTrue();
});

it('lets a deleted folder keep its trashed documents', function () {
    $folder = Docs::companyFolder($this->company);
    $document = DocumentFactory::new()->inFolder($folder)->create();
    $document->delete();

    $this->actingAs($this->owner)->delete(route('documents.folders.destroy', $folder))->assertSessionHasNoErrors();

    expect($document->fresh()->folder_id)->toBe($folder->id);
});

it('reserves the names of standard folders', function () {
    newFolder($this, null, 'vehicle #812')->assertSessionHasErrors('name');
    newFolder($this, null, 'Vehicle 812')->assertSessionHasNoErrors();
});
