<?php

use App\Models\Country;
use App\Models\State;
use App\Models\StoredFile;
use App\Services\Files\StoredFileService;
use App\Support\Fleet\PlateStates;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| Req 3.5 (task 1.5): plate number, plate state and photo on vehicles and trailers. The photo is
| a stored_files row on the private disk, served only through the authorized photo route.
*/

dataset('fleet assets', [
    'vehicles' => ['vehicle'],
    'trailers' => ['trailer'],
]);

beforeEach(function () {
    Storage::fake('local');

    $us = Country::forceCreate(['name' => 'United States', 'iso_code' => 'US']);
    $ca = Country::forceCreate(['name' => 'Canada', 'iso_code' => 'CA']);
    $in = Country::forceCreate(['name' => 'India', 'iso_code' => 'IN']);
    State::forceCreate(['country_id' => $us->id, 'name' => 'Texas', 'code' => 'TX']);
    State::forceCreate(['country_id' => $ca->id, 'name' => 'Ontario', 'code' => 'ON']);
    State::forceCreate(['country_id' => $in->id, 'name' => 'Maharashtra', 'code' => 'MH']);
});

function plateSave($test, $user, string $kind, array $data, $asset = null)
{
    $test->actingAs($user)->withHeaders(FleetAssets::ajax());

    return $asset === null
        ? $test->post(route("admin.$kind.store"), FleetAssets::payload($kind, $data))
        : $test->put(route("admin.$kind.update", $asset->id), FleetAssets::formData($asset, $data));
}

function platePhoto(string $name = 'unit.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 600, 400);
}

describe('validation', function () {
    it('upper-cases and trims the plate and its state', function (string $kind) {
        $owner = Actors::companyOwner();

        plateSave($this, $owner, $kind, ['unit_no' => 'PL-1', 'plate_number' => '  abc 123 ', 'plate_state_code' => 'tx'])->assertOk();

        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'PL-1')->firstOrFail();
        expect($asset->plate_number)->toBe('ABC 123')->and($asset->plate_state_code)->toBe('TX');
    })->with('fleet assets');

    it('accepts a Canadian province', function (string $kind) {
        plateSave($this, Actors::companyOwner(), $kind, ['plate_number' => 'CXRT 123', 'plate_state_code' => 'ON'])->assertOk();
    })->with('fleet assets');

    it('rejects a bad plate, state or photo', function (string $kind, array $data, string $field) {
        plateSave($this, Actors::companyOwner(), $kind, $data)
            ->assertStatus(422)->assertJsonValidationErrors($field);
    })->with('fleet assets')->with([
        'plate over 20 characters' => [['plate_number' => str_repeat('A', 21), 'plate_state_code' => 'TX'], 'plate_number'],
        'plate without a state' => [['plate_number' => 'ABC123'], 'plate_state_code'],
        'unknown state' => [['plate_number' => 'ABC123', 'plate_state_code' => 'ZZ'], 'plate_state_code'],
        'state outside US and Canada' => [['plate_number' => 'ABC123', 'plate_state_code' => 'MH'], 'plate_state_code'],
        'PDF photo' => [['photo' => UploadedFile::fake()->create('plate.pdf', 100, 'application/pdf')], 'photo'],
        'photo over 5 MB' => [['photo' => UploadedFile::fake()->image('big.jpg')->size(5121)], 'photo'],
    ]);

    it('saves no state without a plate number, and allows no plate at all', function (string $kind) {
        $owner = Actors::companyOwner();

        plateSave($this, $owner, $kind, ['unit_no' => 'NS-1', 'plate_state_code' => 'TX'])->assertOk();
        plateSave($this, $owner, $kind, ['unit_no' => 'NS-2'])->assertOk();

        expect(FleetAssets::model($kind)::withoutTenancy()->whereIn('unit_no', ['NS-1', 'NS-2'])->pluck('plate_state_code')->filter()->all())->toBe([]);
    })->with('fleet assets');

    it('clears the state when the plate is cleared, and keeps both when the edit omits the plate', function (string $kind) {
        $owner = Actors::companyOwner();
        $asset = FleetAssets::assetOf($owner, $kind, ['plate_number' => 'ABC123', 'plate_state_code' => 'TX']);

        plateSave($this, $owner, $kind, ['make' => 'Renamed'], $asset)->assertOk();
        expect($asset->fresh()->only(['plate_number', 'plate_state_code']))->toBe(['plate_number' => 'ABC123', 'plate_state_code' => 'TX']);

        plateSave($this, $owner, $kind, ['plate_number' => '', 'plate_state_code' => 'TX'], $asset)->assertOk();
        expect($asset->fresh()->only(['plate_number', 'plate_state_code']))->toBe(['plate_number' => null, 'plate_state_code' => null]);
    })->with('fleet assets');

    it('treats plate and state as one pair when an edit sends only one of them', function (string $kind) {
        $owner = Actors::companyOwner();
        $plated = FleetAssets::assetOf($owner, $kind, ['plate_number' => 'ABC123', 'plate_state_code' => 'TX']);
        $bare = FleetAssets::assetOf($owner, $kind);

        plateSave($this, $owner, $kind, ['plate_state_code' => ''], $plated)->assertOk();
        plateSave($this, $owner, $kind, ['plate_state_code' => 'TX'], $bare)->assertOk();
        plateSave($this, $owner, $kind, ['plate_number' => 'NEW1'], $bare)->assertStatus(422)->assertJsonValidationErrors('plate_state_code');

        expect($plated->fresh()->only(['plate_number', 'plate_state_code']))->toBe(['plate_number' => null, 'plate_state_code' => null])
            ->and($bare->fresh()->only(['plate_number', 'plate_state_code']))->toBe(['plate_number' => null, 'plate_state_code' => null]);
    })->with('fleet assets');
});

describe('photo upload, replace and remove', function () {
    it('stores the photo on create and counts its bytes', function (string $kind) {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $before = (int) $company->fresh()->storage_bytes;
        $photo = platePhoto();

        plateSave($this, $owner, $kind, ['unit_no' => 'PH-1', 'photo' => $photo])->assertOk();

        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'PH-1')->firstOrFail();
        $file = StoredFile::withoutTenancy()->findOrFail($asset->photo_file_id);
        expect($file->company_id)->toBe($company->id)
            ->and($file->path)->toStartWith("companies/{$company->id}/{$kind}s/")
            ->and($file->size_bytes)->toBe($photo->getSize())
            ->and((int) $company->fresh()->storage_bytes)->toBe($before + $photo->getSize());
        Storage::disk('local')->assertExists($file->path);
    })->with('fleet assets');

    it('replaces the photo and purges the old file', function (string $kind) {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $before = (int) $company->fresh()->storage_bytes;
        plateSave($this, $owner, $kind, ['unit_no' => 'PH-2', 'photo' => platePhoto('old.jpg')])->assertOk();
        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'PH-2')->firstOrFail();
        $old = StoredFile::withoutTenancy()->findOrFail($asset->photo_file_id);
        $new = UploadedFile::fake()->image('new.png', 300, 200);

        plateSave($this, $owner, $kind, ['photo' => $new], $asset)->assertOk();

        $asset->refresh();
        expect($asset->photo_file_id)->not->toBe($old->id)
            ->and(StoredFile::withoutTenancy()->find($old->id))->toBeNull()
            ->and((int) $company->fresh()->storage_bytes)->toBe($before + $new->getSize());
        Storage::disk('local')->assertMissing($old->path);
    })->with('fleet assets');

    it('removes the photo and gives the bytes back', function (string $kind) {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $before = (int) $company->fresh()->storage_bytes;
        plateSave($this, $owner, $kind, ['unit_no' => 'PH-3', 'photo' => platePhoto()])->assertOk();
        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'PH-3')->firstOrFail();
        $old = StoredFile::withoutTenancy()->findOrFail($asset->photo_file_id);

        plateSave($this, $owner, $kind, ['remove_photo' => '1'], $asset)->assertOk();

        expect($asset->fresh()->photo_file_id)->toBeNull()
            ->and(StoredFile::withoutTenancy()->find($old->id))->toBeNull()
            ->and((int) $company->fresh()->storage_bytes)->toBe($before);
        Storage::disk('local')->assertMissing($old->path);
    })->with('fleet assets');

    it('keeps the new photo when purging the old one fails after commit', function (string $kind) {
        $owner = Actors::companyOwner();
        plateSave($this, $owner, $kind, ['unit_no' => 'PF-1', 'photo' => platePhoto('old.jpg')])->assertOk();
        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'PF-1')->firstOrFail();
        $oldId = $asset->photo_file_id;
        Exceptions::fake();
        $files = Mockery::mock(StoredFileService::class, [app(TenantContext::class)])->makePartial();
        $files->shouldReceive('purgeIfUnreferenced')->andThrow(new RuntimeException('Lock wait timeout'));
        app()->instance(StoredFileService::class, $files);

        plateSave($this, $owner, $kind, ['photo' => platePhoto('new.jpg')], $asset)->assertOk();

        $new = StoredFile::withoutTenancy()->findOrFail($asset->fresh()->photo_file_id);
        expect($new->id)->not->toBe($oldId);
        Storage::disk('local')->assertExists($new->path);
        Exceptions::assertReported(RuntimeException::class);
    })->with('fleet assets');

    it('keeps the photo when the form is saved without one', function (string $kind) {
        $owner = Actors::companyOwner();
        plateSave($this, $owner, $kind, ['unit_no' => 'PH-4', 'photo' => platePhoto()])->assertOk();
        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'PH-4')->firstOrFail();
        $fileId = $asset->photo_file_id;

        plateSave($this, $owner, $kind, ['make' => 'Renamed'], $asset)->assertOk();

        expect($asset->fresh()->photo_file_id)->toBe($fileId);
    })->with('fleet assets');

    it('refuses an upload from a read-only company', function (string $kind) {
        $company = Actors::readOnly(Actors::company());
        $asset = FleetAssets::assetOf($company->owner, $kind);
        $before = (int) $company->fresh()->storage_bytes;

        plateSave($this, $company->owner, $kind, ['photo' => platePhoto()])->assertForbidden();
        plateSave($this, $company->owner, $kind, ['photo' => platePhoto()], $asset)->assertForbidden();

        expect(StoredFile::withoutTenancy()->where('company_id', $company->id)->count())->toBe(0)
            ->and((int) $company->fresh()->storage_bytes)->toBe($before)
            ->and($asset->fresh()->photo_file_id)->toBeNull();
    })->with('fleet assets');
});

describe('photo route', function () {
    it('serves the photo to its company only', function (string $kind) {
        $owner = Actors::companyOwner();
        plateSave($this, $owner, $kind, ['unit_no' => 'PR-1', 'photo' => platePhoto()])->assertOk();
        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'PR-1')->firstOrFail();

        $this->actingAs($owner)->get(route("admin.$kind.photo", $asset->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(Actors::companyOwner())->get(route("admin.$kind.photo", $asset->id))->assertNotFound();
    })->with('fleet assets');

    it('returns 404 when the asset has no photo', function (string $kind) {
        $owner = Actors::companyOwner();
        $asset = FleetAssets::assetOf($owner, $kind);

        $this->actingAs($owner)->get(route("admin.$kind.photo", $asset->id))->assertNotFound();
    })->with('fleet assets');

    it('gives the edit form the photo url', function (string $kind) {
        $owner = Actors::companyOwner();
        plateSave($this, $owner, $kind, ['unit_no' => 'PR-2', 'photo' => platePhoto()])->assertOk();
        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'PR-2')->firstOrFail();

        $this->actingAs($owner)->getJson(route("admin.$kind.edit", $asset->id))
            ->assertOk()->assertJsonPath('data.photo_url', route("admin.$kind.photo", $asset->id));
    })->with('fleet assets');
});

describe('lists and details', function () {
    it('shows the plate in the table and finds the asset by plate', function (string $kind) {
        $owner = Actors::companyOwner();
        FleetAssets::assetOf($owner, $kind, ['unit_no' => 'LS-1', 'plate_number' => 'XYZ9876', 'plate_state_code' => 'TX']);
        FleetAssets::assetOf($owner, $kind, ['unit_no' => 'LS-2']);

        $rows = $this->actingAs($owner)->withHeaders(FleetAssets::ajax())
            ->get(route("admin.$kind.index", ['draw' => 1, 'search' => ['value' => 'xyz98']]))
            ->assertOk()->json('data');

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['plate'])->toContain('XYZ9876')->toContain('TX')
            ->and($rows[0]['action'])->toContain(route("admin.$kind.show", $rows[0]['id']));
    })->with('fleet assets');

    it('shows the plate, state and photo on the details page, not indexed', function (string $kind) {
        $owner = Actors::companyOwner();
        plateSave($this, $owner, $kind, ['unit_no' => 'DT-1', 'plate_number' => 'DET 42', 'plate_state_code' => 'ON', 'photo' => platePhoto()])->assertOk();
        $asset = FleetAssets::model($kind)::withoutTenancy()->where('unit_no', 'DT-1')->firstOrFail();

        $this->actingAs($owner)->get(route("admin.$kind.show", $asset->id))
            ->assertOk()
            ->assertSee('DET 42')
            ->assertSee('ON')
            ->assertSee(route("admin.$kind.photo", $asset->id), false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->actingAs(Actors::companyOwner())->get(route("admin.$kind.show", $asset->id))->assertNotFound();
    })->with('fleet assets');
});

it('offers only two-letter state codes in the form', function () {
    State::forceCreate(['country_id' => Country::where('iso_code', 'US')->value('id'), 'name' => 'Baker Island', 'code' => 'UM-81']);
    State::forceCreate(['country_id' => Country::where('iso_code', 'US')->value('id'), 'name' => 'No Code', 'code' => null]);

    expect(PlateStates::options())->toBe(['United States' => ['TX' => 'Texas'], 'Canada' => ['ON' => 'Ontario']]);
});
