<?php

use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Services\Compliance\DriverComplianceService;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Support\Actors;

/*
| DCMP-05 (decided 2026-10-10: option B): the dashboard computes each driver's compliance
| status in SQL, so the summary cards cover every active driver while the list is paginated
| and only the current page's documents are loaded. The SQL must give the same answer as
| DriverComplianceService::calculateCompliance(), which still builds the per-document rows.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(now()->setTime(15, 30));
});

describe('DCMP-05: paginated dashboard', function () {
    it('shows 25 drivers per page and counts every active driver in the cards', function () {
        [$owner] = DriverCompliance::tenantWithDriver();
        foreach (range(1, 29) as $i) {
            DriverCompliance::driverOf($owner);
        }
        foreach (['pending', 'draft', 'rejected'] as $status) {
            DriverCompliance::driverOf($owner, ['status' => $status]);
        }
        DriverCompliance::driverOf($owner)->delete();
        DriverCompliance::tenantWithDriver();
        DriverCompliance::documentType();

        $first = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk();
        $second = $this->actingAs($owner)->get(route('admin.compliance.drivers', ['page' => 2]))->assertOk();

        expect($first->viewData('drivers')->count())->toBe(25)
            ->and($second->viewData('drivers')->count())->toBe(5)
            ->and($first->viewData('totalDrivers'))->toBe(30)
            ->and($first->viewData('totalCritical'))->toBe(30)
            ->and($first->getContent())->toContain('page=2');
    });

    it('counts the cards over all pages, not just the one shown', function () {
        [$owner] = DriverCompliance::tenantWithDriver(['first_name' => 'Aaron']);
        $type = DriverCompliance::documentType();
        foreach (range(1, 26) as $i) {
            DriverCompliance::driverOf($owner, ['first_name' => 'Zed'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }
        // Only the last drivers alphabetically (page 2) are compliant / warning.
        DriverCompliance::document(Driver::where('first_name', 'Zed26')->first(), $type);
        DriverCompliance::document(Driver::where('first_name', 'Zed25')->first(), $type, ['expiry_date' => now()->addDays(5)->toDateString()]);

        $response = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk();

        expect($response->viewData('totalCompliant'))->toBe(1)
            ->and($response->viewData('totalWarning'))->toBe(1)
            ->and($response->viewData('totalCritical'))->toBe(25)
            ->and($response->viewData('overallCompliance'))->toEqual(round(1 / 27 * 100, 1));
    });

    it('applies each company\'s switched-off types for a super-admin', function () {
        $type = DriverCompliance::documentType();
        [$optedOut, $a] = DriverCompliance::tenantWithDriver();
        [, $b] = DriverCompliance::tenantWithDriver();
        DB::table('company_document_type')->insert([
            'company_id' => Actors::companyOf($optedOut)->id,
            'document_type_id' => $type->id,
        ]);

        $response = $this->actingAs(Actors::superAdmin())->get(route('admin.compliance.drivers'))->assertOk();

        expect($response->viewData('totalCompliant'))->toBe(1)
            ->and($response->viewData('totalCritical'))->toBe(1);
        $statuses = collect($response->viewData('drivers')->items())->pluck('compliance_status', 'id');
        expect($statuses[$a->id])->toBe('compliant')->and($statuses[$b->id])->toBe('danger');
    });

    it('gives the same counts in SQL as the PHP calculation, at every boundary', function () {
        $types = [
            'a' => DriverCompliance::documentType(['name' => 'A']),
            'b' => DriverCompliance::documentType(['name' => 'B']),
            'c' => DriverCompliance::documentType(['name' => 'C']),
        ];
        DocumentTypeFactory::new()->inactive()->create();
        $vehicleType = DocumentTypeFactory::new()->module('vehicle')->create();
        [$owner] = DriverCompliance::tenantWithDriver();
        DB::table('company_document_type')->insert(['company_id' => Actors::companyOf($owner)->id, 'document_type_id' => $types['c']->id]);
        $otherCompany = Actors::companyOf(Actors::companyOwner());

        $expiries = [
            'expired yesterday' => now()->subDay(),
            'expires today' => now(),
            'expires tomorrow' => now()->addDay(),
            'expires in 30 days' => now()->addDays(30),
            'expires in 31 days' => now()->addDays(31),
            'no expiry' => null,
        ];

        foreach ([Actors::companyOf($owner), $otherCompany] as $company) {
            DriverFactory::new()->forCompany($company)->create();
            foreach ($expiries as $expiry) {
                foreach ($types as $type) {
                    $driver = DriverFactory::new()->forCompany($company)->create();
                    DriverCompliance::document($driver, $type, ['expiry_date' => $expiry?->toDateString()]);
                    DriverCompliance::document($driver, $types['a'] === $type ? $types['b'] : $types['a'], ['expiry_date' => now()->addYear()->toDateString()]);
                    DriverCompliance::document($driver, $vehicleType, ['expiry_date' => now()->subYear()->toDateString()]);
                }
            }
            // Two documents of one type: the oldest one counts, in PHP and in SQL.
            $duplicate = DriverFactory::new()->forCompany($company)->create();
            DriverCompliance::document($duplicate, $types['a'], ['expiry_date' => now()->subDay()->toDateString()]);
            DriverCompliance::document($duplicate, $types['a'], ['expiry_date' => now()->addYear()->toDateString()]);
        }

        $service = app(DriverComplianceService::class);
        $rows = $service->withComplianceCounts(Driver::query())->get();
        $phpStatuses = ['compliant' => 0, 'warning' => 0, 'danger' => 0];

        expect($rows)->toHaveCount(Driver::count());
        foreach ($rows as $row) {
            $php = $service->forDriver(Driver::with('documents')->findOrFail($row->id));
            $phpStatuses[$php['status']]++;

            expect([(int) $row->total_docs, (int) $row->valid_docs, (int) $row->expiring_docs])
                ->toBe([$php['total_docs'], $php['compliant_docs'], count($php['expiring_documents'])]);
        }

        $summary = $service->summary(Driver::query());
        expect($summary)->toBe(['total' => array_sum($phpStatuses)] + $phpStatuses)
            ->and($phpStatuses['warning'])->toBeGreaterThan(0)
            ->and($phpStatuses['compliant'])->toBeGreaterThan(0)
            ->and($phpStatuses['danger'])->toBeGreaterThan(0);
    });

    it('loads only the current page\'s documents', function () {
        [$owner] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();
        foreach (range(1, 30) as $i) {
            DriverCompliance::document(DriverCompliance::driverOf($owner), $type);
        }

        DB::enableQueryLog();
        $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk();
        $documentQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_starts_with($q['query'], 'select * from "driver_compliance_documents"'));
        DB::disableQueryLog();

        // Integer keys are inlined in the eager-load's "in (...)", not bound.
        expect($documentQueries)->toHaveCount(1);
        preg_match('/"driver_id" in \(([^)]*)\)/', $documentQueries->first()['query'], $ids);
        expect(explode(',', $ids[1]))->toHaveCount(25);
        expect(DriverComplianceDocument::count())->toBe(30);
    });
});
