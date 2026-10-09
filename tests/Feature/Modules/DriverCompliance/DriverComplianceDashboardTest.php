<?php

use App\Models\DriverComplianceDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Support\Perf;

/*
| DCMP-03 (dashboard side): a driver whose only problem is a document expiring soon is counted
| under Warning, not Critical.
| DCMP-05: the dashboard's query count must not grow with the number of drivers, and an
| upload can't set an expiry date in the past (the form's date picker and the fleet upload
| already require today or later).
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

describe('DCMP-03: dashboard counts', function () {
    it('counts a driver with only an expiring document as Warning', function () {
        [$owner, $expiring] = DriverCompliance::tenantWithDriver();
        $valid = DriverCompliance::driverOf($owner);
        $missing = DriverCompliance::driverOf($owner);
        $type = DriverCompliance::documentType();
        DriverCompliance::document($expiring, $type, ['expiry_date' => now()->addDays(10)->toDateString()]);
        DriverCompliance::document($valid, $type);

        $response = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk();

        expect($response->viewData('totalWarning'))->toBe(1)
            ->and($response->viewData('totalCritical'))->toBe(1)
            ->and($response->viewData('totalCompliant'))->toBe(1);
        $statuses = collect($response->viewData('drivers')->items())->pluck('compliance_status', 'id');
        expect($statuses[$expiring->id])->toBe('warning')
            ->and($statuses[$valid->id])->toBe('compliant')
            ->and($statuses[$missing->id])->toBe('danger');
    });

    it('returns warning from the details endpoint too', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        DriverCompliance::document($driver, DriverCompliance::documentType(), ['expiry_date' => now()->addDays(10)->toDateString()]);

        $this->actingAs($owner)->getJson(route('admin.compliance.driver.details', $driver->id))
            ->assertOk()
            ->assertJsonPath('driver.compliance_status', 'warning');
    });
});

describe('DCMP-05: dashboard load and expiry dates', function () {
    it('loads the dashboard in a constant number of queries', function () {
        [$owner] = DriverCompliance::tenantWithDriver();
        $types = [DriverCompliance::documentType(), DriverCompliance::documentType()];

        Perf::assertConstantQueries(
            function (int $n) use ($owner, $types) {
                for ($i = 0; $i < $n; $i++) {
                    $driver = DriverCompliance::driverOf($owner);
                    DriverCompliance::document($driver, $types[$i % 2]);
                }
            },
            fn () => $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk(),
        );
    });

    it('rejects an expiry date in the past', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs($owner)
            ->post(
                route('admin.compliance.driver.documents.upload'),
                DriverCompliance::payload($driver, $type, ['expiry_date' => now()->subDay()->toDateString()]),
                DriverCompliance::json()
            )
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        expect(DriverComplianceDocument::count())->toBe(0);
    });

    it('still accepts today, or no expiry date at all', function (?string $expiry) {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();

        $this->actingAs($owner)
            ->post(
                route('admin.compliance.driver.documents.upload'),
                DriverCompliance::payload($driver, DriverCompliance::documentType(), ['expiry_date' => $expiry]),
                DriverCompliance::json()
            )
            ->assertOk();
    })->with([
        'today' => fn () => now()->toDateString(),
        'none' => null,
    ]);
});
