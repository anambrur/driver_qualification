<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Modules\Drivers\DriverAdmin;

/*
| DRV-06: the edit form re-posts the driver's status in a hidden field and UpdateDriverRequest
| accepted any status. A tenant could change it to `active` (or `rejected`) and skip the
| hire workflow, its checks (driver must be pending), its emails and its audit fields
| (hired_at, action_by, rejection reason). The only way to hire or reject is now
| POST admin.driver.hire-status.
*/

describe('DRV-06: the edit form cannot change a driver\'s status', function () {
    it('ignores a status sent with the edit form', function (string $tampered) {
        Mail::fake();
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'pending']);

        $this->actingAs($owner)
            ->put(route('admin.driver.update', $driver->id), DriverAdmin::updatePayload($driver, ['status' => $tampered]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $fresh = $driver->fresh();
        expect($fresh->status)->toBe('pending')
            ->and($fresh->hired_at)->toBeNull()
            ->and($fresh->action_by)->toBeNull()
            ->and($fresh->first_name)->toBe('Jane');
        Mail::assertNothingQueued();
    })->with(['active', 'rejected', 'draft', 'approved']);

    it('still saves the edit form without a status field', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'active']);
        $payload = DriverAdmin::updatePayload($driver, ['first_name' => 'Renamed']);
        unset($payload['status']);

        $this->actingAs($owner)->put(route('admin.driver.update', $driver->id), $payload)->assertSessionHasNoErrors();

        expect($driver->fresh())->status->toBe('active')->first_name->toBe('Renamed');
    });

    it('no longer has a separate status endpoint (removed 2026-10-09)', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'pending']);

        expect(Route::has('admin.driver.update.status'))->toBeFalse();
        $this->actingAs($owner)
            ->postJson('/admin/driver/'.$driver->id.'/status', ['status' => 'approved'])
            ->assertNotFound();

        expect($driver->fresh()->status)->toBe('pending');
    });

    it('still hires a pending driver through the hire workflow', function () {
        Mail::fake();
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'pending']);

        $this->actingAs($owner)
            ->postJson(route('admin.driver.hire-status', $driver->id), [
                'action' => 'hire', 'hire_date' => now()->toDateString(), 'hazmat' => 'no', 'lcv_certificate' => 'no',
            ])->assertOk();

        expect($driver->fresh())->status->toBe('active')->action_by->toBe($owner->id);
    });
});
