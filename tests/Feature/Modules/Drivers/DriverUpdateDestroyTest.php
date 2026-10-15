<?php

use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Violation;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;

/*
| DRV-08: update() deletes and re-inserts every child row on each save. The violation rows
| carried the applicant's step-5 signature, which the re-insert dropped, so the step-5 page
| showed "N/A" after any edit. Since F19c the signature and the yes/no answer live on
| driver_documents and violation rows exist only for real events. destroy() returned the
| exception text to the browser.
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

        $document = DriverDocument::where('driver_id', $driver->id)->sole();
        expect(Violation::where('driver_id', $driver->id)->count())->toBe(0)
            ->and($document->has_violations)->toBeFalse()
            ->and($document->violation_record_signature)->toBe('Jane Roe')
            ->and((string) $document->violation_record_date_signed)->toStartWith('2026-01-15');
    });

    it('keeps the signature when the edit form changes the violations', function () {
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

        $document = DriverDocument::where('driver_id', $driver->id)->sole();
        expect(Violation::where('driver_id', $driver->id)->count())->toBe(2)
            ->and($document->has_violations)->toBeTrue()
            ->and($document->violation_record_signature)->toBe('Jane Roe');
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
        Driver::deleting(fn () => throw new RuntimeException('SQLSTATE[23000] secret detail'));

        $response = $this->actingAs($owner)->deleteJson(route('admin.driver.destroy', $driver->id));

        $response->assertStatus(500)->assertJson(['success' => false]);
        expect($response->getContent())->not->toContain('SQLSTATE');
    });

    it('returns 404 for another tenant\'s driver and keeps it', function () {
        [, $driver] = DriverAdmin::tenantWithDriver();

        $this->actingAs(Actors::companyOwner())->deleteJson(route('admin.driver.destroy', $driver->id))->assertNotFound();

        expect($driver->fresh()->trashed())->toBeFalse();
    });

    it('still deletes the tenant\'s own driver', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();

        $this->actingAs($owner)->deleteJson(route('admin.driver.destroy', $driver->id))
            ->assertOk()->assertJson(['success' => true]);

        expect(Driver::withoutTenancy()->find($driver->id))->toBeNull();
    });
});
