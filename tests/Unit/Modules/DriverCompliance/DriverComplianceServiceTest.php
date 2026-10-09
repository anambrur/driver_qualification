<?php

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Services\Compliance\DriverComplianceService;
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
 */
function complianceFor(array $expiries): array
{
    $types = collect();
    $documents = collect();

    foreach (array_values($expiries) as $i => $expiry) {
        $type = (new DocumentType)->forceFill(['id' => $i + 1, 'name' => "Type {$i}", 'module' => 'driver', 'status' => true]);
        $types->push($type);

        if ($expiry !== 'missing') {
            $documents->push((new DriverComplianceDocument)->forceFill([
                'id' => $i + 1,
                'document_type_id' => $type->id,
                'expiry_date' => $expiry,
                'file_path' => "documents/drivers/{$i}.pdf",
            ]));
        }
    }

    $driver = (new Driver)->forceFill(['id' => 1, 'company_id' => 1])->setRelation('documents', $documents);

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

    it('still counts only fully valid documents in the percentage', function () {
        expect(complianceFor(['2027-10-09', '2026-10-20'])['percentage'])->toEqual(50);
    });
});
