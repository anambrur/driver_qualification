<?php

use App\Models\Violation;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;

/*
| DRV-08: update() deletes and re-inserts every child row on each save. The violation rows
| carry the applicant's step-5 signature, which the re-insert dropped, so the step-5 page
| showed "N/A" after any edit. destroy() returned the exception text to the browser.
| (Whether destroy should hard-delete a DQ file is deferred to the product owner.)
*/

describe('DRV-08: saving the edit form keeps the violation signature', function () {
    it('keeps the signature when the driver had no violations', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'pending']);
        $this->actingAs($owner)->post(route('admin.driver.violation.store'), [
            'driver_id' => $driver->id, 'violation' => 'no',
            'applicant_signature' => 'Jane Roe', 'date_signed' => '2026-01-15',
        ])->assertSessionHasNoErrors();

        $this->put(route('admin.driver.update', $driver->id), DriverAdmin::updatePayload($driver))
            ->assertSessionHasNoErrors();

        $rows = Violation::where('driver_id', $driver->id)->get();
        expect($rows)->toHaveCount(1)
            ->and($rows[0]->violation)->toBe('no')
            ->and($rows[0]->violation_record_signature)->toBe('Jane Roe')
            ->and((string) $rows[0]->violation_record_date_signed)->toStartWith('2026-01-15');
    });

    it('keeps the signature on every violation row', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'pending']);
        $this->actingAs($owner)->post(route('admin.driver.violation.store'), [
            'driver_id' => $driver->id, 'violation' => 'yes',
            'violation_date' => ['2024-05-01'], 'violation_location' => ['Austin'], 'offense' => ['Speeding'], 'vehicle_type' => ['Truck'],
            'applicant_signature' => 'Jane Roe', 'date_signed' => '2026-01-15',
        ])->assertSessionHasNoErrors();

        $this->put(route('admin.driver.update', $driver->id), DriverAdmin::updatePayload($driver, [
            'violation' => 'yes',
            'violation_date' => ['2024-05-01', '2025-02-02'], 'violation_location' => ['Austin', 'Dallas'],
            'offense' => ['Speeding', 'Lane'], 'vehicle_type' => ['Truck', 'Truck'],
        ]))->assertSessionHasNoErrors();

        $rows = Violation::where('driver_id', $driver->id)->get();
        expect($rows)->toHaveCount(2)
            ->and($rows->pluck('violation_record_signature')->unique()->all())->toBe(['Jane Roe']);
    });
});

describe('DRV-08: destroy does not leak exception text', function () {
    it('returns 404, not a 500 with the exception text, for an unknown driver', function () {
        $owner = Actors::companyOwner();

        $response = $this->actingAs($owner)->deleteJson(route('admin.driver.destroy', 999999));

        $response->assertNotFound();
        expect($response->getContent())->not->toContain('Error deleting driver');
    });

    it('logs a failed delete and returns a generic message', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        \App\Models\Driver::deleting(fn () => throw new RuntimeException('SQLSTATE[23000] secret detail'));

        $response = $this->actingAs($owner)->deleteJson(route('admin.driver.destroy', $driver->id));

        $response->assertStatus(500)->assertJson(['success' => false]);
        expect($response->getContent())->not->toContain('SQLSTATE');
    });

    it('still returns 403 for another tenant\'s driver and keeps it', function () {
        [, $driver] = DriverAdmin::tenantWithDriver();

        $this->actingAs(Actors::companyOwner())->deleteJson(route('admin.driver.destroy', $driver->id))->assertForbidden();

        expect($driver->fresh()->trashed())->toBeFalse();
    });

    it('still deletes the tenant\'s own driver', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();

        $this->actingAs($owner)->deleteJson(route('admin.driver.destroy', $driver->id))
            ->assertOk()->assertJson(['success' => true]);

        expect(\App\Models\Driver::find($driver->id))->toBeNull();
    });
});
