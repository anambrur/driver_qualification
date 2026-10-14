<?php

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Models\License;
use App\Services\Compliance\DriverComplianceService;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverComplianceDocumentFactory;
use Database\Factories\DriverFactory;
use Illuminate\Support\Carbon;

/*
| DCMP-03: a document expiring within 30 days isn't counted as compliant, so the percentage
| drops below 100 and the old `percentage < 100` check made the status `danger`. The
| `warning` status could never be returned. Missing/expired = danger, only expiring = warning
| (the rule the fleet dashboard already documents). The percentage still counts only fully
| valid documents.
*/

beforeEach(fn () => Carbon::setTestNow('2026-10-09 12:00:00'));
afterEach(fn () => Carbon::setTestNow());

/**
 * @param  array<int, ?string>  $expiries  one entry per required type; null = no expiry, 'missing' = no document
 * @param  ?string  $licenseExpires  the current licence's expiry; null = no current licence
 */
function complianceFor(array $expiries, ?string $licenseExpires = '2028-01-01', bool $requiresExpiry = false): array
{
    $types = collect();
    $documents = collect();

    foreach (array_values($expiries) as $i => $expiry) {
        $type = (new DocumentType)->forceFill(['id' => $i + 1, 'name' => "Type {$i}", 'module' => 'driver', 'status' => true, 'requires_expiry' => $requiresExpiry]);
        $types->push($type);

        if ($expiry !== 'missing') {
            $documents->push((new DriverComplianceDocument)->forceFill([
                'id' => $i + 1,
                'document_type_id' => $type->id,
                'expiry_date' => $expiry,
            ]));
        }
    }

    $license = $licenseExpires === null ? null : (new License)->forceFill(['expires' => $licenseExpires, 'is_current' => true]);
    $driver = (new Driver)->forceFill(['id' => 1, 'company_id' => 1])
        ->setRelation('documents', $documents)
        ->setRelation('currentLicense', $license);

    return app(DriverComplianceService::class)->calculateCompliance($driver, $types);
}

describe('DCMP-03: compliance status', function () {
    it('is warning when the only problem is a document expiring soon', function () {
        $result = complianceFor(['2027-10-09', '2026-10-20', null]);

        expect($result['status'])->toBe('warning')
            ->and($result['expiring_documents'])->toHaveCount(1)
            ->and($result['missing_documents'])->toBe([]);
    });

    it('is danger when a document is missing', function () {
        expect(complianceFor(['2027-10-09', 'missing'])['status'])->toBe('danger');
    });

    it('is danger when a document is expired, even if another one is only expiring', function () {
        expect(complianceFor(['2026-10-01', '2026-10-20'])['status'])->toBe('danger');
    });

    it('is compliant at 100% when every document is valid', function () {
        $result = complianceFor(['2027-10-09', null]);

        expect($result['status'])->toBe('compliant')->and($result['percentage'])->toEqual(100);
    });

    it('still counts only fully valid requirements in the percentage', function () {
        // two documents plus the CDL; only one document and the CDL are fully valid
        expect(complianceFor(['2027-10-09', '2026-10-20'])['percentage'])->toEqual(66.7);
    });
});

describe('F17: medical certificate and CDL expiry', function () {
    it('counts a document without an expiry date as missing when its type requires one', function () {
        $result = complianceFor([null], requiresExpiry: true);

        expect($result['status'])->toBe('danger')
            ->and($result['missing_documents'])->toBe(['Type 0 (no expiry date)']);
    });

    it('treats an expired medical certificate like any expired document', function () {
        $result = complianceFor(['2026-10-01'], requiresExpiry: true);

        expect($result['status'])->toBe('danger')
            ->and($result['missing_documents'])->toBe(['Type 0 (expired)']);
    });

    it('is danger when the driver has no current licence', function () {
        $result = complianceFor(['2027-10-09'], licenseExpires: null);

        expect($result['status'])->toBe('danger')
            ->and($result['total_docs'])->toBe(2)
            ->and($result['missing_documents'])->toBe(["Commercial Driver's License"]);
    });

    it('is danger when the current licence has expired', function () {
        expect(complianceFor(['2027-10-09'], licenseExpires: '2026-10-09')['missing_documents'])
            ->toBe(["Commercial Driver's License (expired)"]);
    });

    it('is warning when the current licence expires within 30 days', function () {
        $result = complianceFor(['2027-10-09'], licenseExpires: '2026-11-01');

        expect($result['status'])->toBe('warning')
            ->and(collect($result['document_details'])->firstWhere('type_key', 'cdl')['status'])->toBe('expiring');
    });

    it('gives the same counts in SQL as in PHP', function () {
        $medical = DocumentTypeFactory::new()->requiresExpiry()->create(['system_key' => 'medical_examiner_certificate']);
        $other = DocumentTypeFactory::new()->create();

        $drivers = [
            'compliant' => DriverFactory::new()->withCurrentLicense('2028-01-01')->create(),
            'no licence' => DriverFactory::new()->create(),
            'licence expiring' => DriverFactory::new()->withCurrentLicense('2026-10-25')->create(),
            'licence expired' => DriverFactory::new()->withCurrentLicense('2026-10-09')->create(),
            'medical without expiry' => DriverFactory::new()->withCurrentLicense('2028-01-01')->create(),
        ];

        foreach ($drivers as $label => $driver) {
            DriverComplianceDocumentFactory::new()->forDriver($driver, $other)->create(['expiry_date' => null]);
            DriverComplianceDocumentFactory::new()->forDriver($driver, $medical)->create([
                'expiry_date' => $label === 'medical without expiry' ? null : '2027-06-01',
            ]);
        }

        $service = app(DriverComplianceService::class);
        $rows = $service->withComplianceCounts(Driver::query())->get()->keyBy('id');

        foreach ($drivers as $label => $driver) {
            $php = $service->forDriver(Driver::findOrFail($driver->id));
            $sql = $rows[$driver->id];
            $sqlStatus = $sql->total_docs - $sql->valid_docs - $sql->expiring_docs > 0 ? 'danger' : ($sql->expiring_docs > 0 ? 'warning' : 'compliant');

            expect([(int) $sql->total_docs, (int) $sql->valid_docs, $sqlStatus])
                ->toBe([$php['total_docs'], $php['compliant_docs'], $php['status']], $label);
        }

        expect($service->summary(Driver::query()))
            ->toBe(['total' => 5, 'compliant' => 1, 'warning' => 1, 'danger' => 3]);
    });
});
