<?php

use App\Models\AssetGroup;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Violation;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;

/*
| DRV-08 (decided 2026-10-09: soft delete). Deleting a driver used to hard-delete the whole
| driver qualification file (cascade) and orphan the uploaded files. FMCSA requires keeping a
| DQ file for 3 years after employment ends, so a delete now only hides the driver: the
| record, its child rows, compliance documents and files are kept.
*/

describe('DRV-08: deleting a driver keeps the qualification file', function () {
    it('soft-deletes the driver and keeps its records and files', function () {
        Storage::fake('local');
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        Violation::create(['driver_id' => $driver->id, 'violation' => 'no', 'violation_record_signature' => 'Jane Roe']);
        DriverDocument::create(['driver_id' => $driver->id, 'license_front' => 'images/documents/f.png']);
        Storage::disk('local')->put('images/documents/f.png', 'front');

        $this->actingAs($owner)->deleteJson(route('admin.driver.destroy', $driver->id))
            ->assertOk()->assertJson(['success' => true]);

        expect(Driver::find($driver->id))->toBeNull()
            ->and(Driver::withTrashed()->find($driver->id)->deleted_at)->not->toBeNull()
            ->and(DB::table('violations')->where('driver_id', $driver->id)->count())->toBe(1)
            ->and(DB::table('driver_documents')->where('driver_id', $driver->id)->count())->toBe(1);
        Storage::disk('local')->assertExists('images/documents/f.png');
    });

    it('hides a deleted driver from the list, the counts and every driver page', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'pending']);
        DriverAdmin::driverOf($owner, ['status' => 'active']);
        $driver->delete();
        $this->actingAs($owner);

        expect($this->get(route('admin.driver.index'))->viewData('statusCounts'))
            ->toMatchArray(['all' => 1, 'pending' => 0, 'active' => 1]);
        $this->withHeaders(DriverAdmin::ajax())->get(route('admin.driver.index', ['draw' => 1]))
            ->assertJsonCount(1, 'data');

        $this->get(route('admin.driver.show', $driver->id))->assertNotFound();
        $this->get(route('admin.driver.edit', $driver->id))->assertNotFound();
        $this->get(route('admin.driver.license', $driver->id))->assertNotFound();
        $this->get(route('admin.driver.file', [$driver->id, 'photo']))->assertNotFound();
        $this->deleteJson(route('admin.driver.destroy', $driver->id))->assertNotFound();
        $this->postJson(route('admin.driver.hire-status', $driver->id), [
            'action' => 'hire', 'hire_date' => now()->toDateString(), 'hazmat' => 'no', 'lcv_certificate' => 'no',
        ])->assertNotFound();
    });

    it('soft-deletes the driver\'s asset groups, as the old cascade removed them', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        $group = AssetGroupFactory::new()->forCompany(DriverAdmin::companyOf($owner))->create(['driver_id' => $driver->id]);
        $other = AssetGroupFactory::new()->forCompany(DriverAdmin::companyOf($owner))->create();

        $this->actingAs($owner)->deleteJson(route('admin.driver.destroy', $driver->id))->assertOk();

        expect(AssetGroup::find($group->id))->toBeNull()
            ->and(AssetGroup::withTrashed()->find($group->id))->not->toBeNull()
            ->and(AssetGroup::find($other->id))->not->toBeNull();
    });

    it('does not let a deleted driver be put in an asset group', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        $vehicle = VehicleFactory::new()->forCompany(DriverAdmin::companyOf($owner))->create();
        $driver->delete();

        $this->actingAs($owner)->postJson(route('admin.asset-group.store'), [
            'group_name' => 'G1', 'status' => 'active', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
        ])->assertStatus(422)->assertJsonValidationErrors('driver_id');
    });

    it('explains, instead of failing on insert, when the email belongs to a deleted driver', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['email' => 'kept@example.com']);
        $driver->delete();

        $this->actingAs($owner)
            ->post(route('admin.driver.store'), DriverAdmin::payload(['email' => 'kept@example.com']))
            ->assertSessionHasErrors('email');

        $this->actingAs(Actors::companyOwner())
            ->post(route('admin.driver.store'), DriverAdmin::payload(['email' => 'kept@example.com']))
            ->assertSessionHasNoErrors();
    });

    it('still lets a driver keep their own email on edit, but not take another driver\'s', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        $taken = DriverAdmin::driverOf($owner);
        $this->actingAs($owner);

        $this->put(route('admin.driver.update', $driver->id), DriverAdmin::updatePayload($driver))
            ->assertSessionHasNoErrors();
        $this->put(route('admin.driver.update', $driver->id), DriverAdmin::updatePayload($driver, ['email' => $taken->email]))
            ->assertSessionHasErrors('email');
    });

    it('lets a super-admin delete any company\'s driver, softly', function () {
        $driver = DriverFactory::new()->create();

        $this->actingAs(Actors::superAdmin())->deleteJson(route('admin.driver.destroy', $driver->id))->assertOk();

        expect(Driver::withTrashed()->find($driver->id)->trashed())->toBeTrue();
    });
});
