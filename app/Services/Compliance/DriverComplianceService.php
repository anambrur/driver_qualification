<?php

namespace App\Services\Compliance;

use App\Models\DocumentType;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DriverComplianceService
{
    /** A document expiring within this many days is "expiring", not "valid". */
    private const EXPIRING_WITHIN_DAYS = 30;

    /**
     * Calculate compliance for a driver using active driver document types.
     */
    public function forDriver(Driver $driver): array
    {
        if (!$driver->relationLoaded('documents')) {
            $driver->load('documents.documentType');
        }

        $documentTypes = DocumentType::where('module', 'driver')
            ->enabledForCompany($driver->company_id)
            ->orderBy('name')
            ->get();

        return $this->calculateCompliance($driver, $documentTypes);
    }

    /**
     * Calculate compliance for a driver against a set of document types.
     *
     * @param  Collection<int, DocumentType>  $documentTypes
     */
    public function calculateCompliance(Driver $driver, Collection $documentTypes): array
    {
        $totalDocs = $documentTypes->count();
        $compliantDocs = 0;
        $missingDocs = [];
        $expiringDocs = [];
        $documentDetails = [];

        // The oldest document of each type counts, as in withComplianceCounts().
        $documents = $driver->documents->sortBy('id');

        foreach ($documentTypes as $docType) {
            $document = $documents->firstWhere('document_type_id', $docType->id);

            $docStatus = [
                'type_id' => $docType->id,
                'type_name' => $docType->name,
                'status' => 'missing',
                'file_date' => null,
                'expiry_date' => null,
                'days_until_expiry' => null,
                'document_id' => null,
                'file_path' => null,
                'description' => null,
                'updated_at' => null,
                'created_at' => null,
            ];

            if ($document) {
                $docStatus['file_date'] = $document->file_date;
                $docStatus['expiry_date'] = $document->expiry_date;
                $docStatus['document_id'] = $document->id;
                $docStatus['file_path'] = $document->file_path;
                $docStatus['description'] = $document->description;
                $docStatus['updated_at'] = $document->updated_at;
                $docStatus['created_at'] = $document->created_at;

                if ($document->expiry_date) {
                    $expiryDate = Carbon::parse($document->expiry_date);
                    $today = Carbon::today();
                    $daysUntilExpiry = $today->diffInDays($expiryDate, false);

                    $docStatus['days_until_expiry'] = $daysUntilExpiry;

                    if ($expiryDate->isFuture()) {
                        if ($daysUntilExpiry <= 30) {
                            $docStatus['status'] = 'expiring';
                            $expiringDocs[] = $docType->name . ' (expires in ' . $daysUntilExpiry . ' days)';
                        } else {
                            $docStatus['status'] = 'valid';
                            $compliantDocs++;
                        }
                    } else {
                        $docStatus['status'] = 'expired';
                        $missingDocs[] = $docType->name . ' (expired)';
                    }
                } else {
                    $docStatus['status'] = 'valid';
                    $compliantDocs++;
                }
            } else {
                $missingDocs[] = $docType->name;
            }

            $documentDetails[] = $docStatus;
        }

        $percentage = $totalDocs > 0 ? round(($compliantDocs / $totalDocs) * 100, 1) : 0;

        // Missing/expired = danger; only expiring soon = warning; all valid = compliant.
        // The percentage counts only fully valid docs, so it can't decide the status.
        $status = 'compliant';
        if (count($missingDocs) > 0) {
            $status = 'danger';
        } elseif (count($expiringDocs) > 0) {
            $status = 'warning';
        }

        return [
            'total_docs' => $totalDocs,
            'compliant_docs' => $compliantDocs,
            'percentage' => $percentage,
            'missing_documents' => $missingDocs,
            'expiring_documents' => $expiringDocs,
            'document_details' => $documentDetails,
            'status' => $status,
        ];
    }

    /**
     * Add total_docs, valid_docs and expiring_docs to a driver query, computed in SQL with the
     * same rules as calculateCompliance(): required = active driver types the driver's company
     * hasn't switched off; expired = expiry today or earlier; expiring = within 30 days.
     *
     * @param  Builder<Driver>  $drivers
     * @return Builder<Driver>
     */
    public function withComplianceCounts(Builder $drivers): Builder
    {
        // Dates are compared as Y-m-d strings with < / >=, which works for both a MySQL DATE
        // and the "Y-m-d 00:00:00" text SQLite stores.
        $tomorrow = Carbon::today()->addDay()->toDateString();
        $validFrom = Carbon::today()->addDays(self::EXPIRING_WITHIN_DAYS + 1)->toDateString();

        return $drivers->addSelect([
            'total_docs' => $this->requiredTypes()->selectRaw('count(*)'),
            'valid_docs' => $this->requiredTypesWithDocument()->selectRaw('count(*)')
                ->where(fn ($q) => $q->whereNull('dcd.expiry_date')->orWhere('dcd.expiry_date', '>=', $validFrom)),
            'expiring_docs' => $this->requiredTypesWithDocument()->selectRaw('count(*)')
                ->where('dcd.expiry_date', '>=', $tomorrow)
                ->where('dcd.expiry_date', '<', $validFrom),
        ]);
    }

    /**
     * Number of drivers per compliance status, computed in SQL over the whole query.
     *
     * @param  Builder<Driver>  $drivers
     * @return array{total: int, compliant: int, warning: int, danger: int}
     */
    public function summary(Builder $drivers): array
    {
        $counts = $this->withComplianceCounts($drivers->clone()->reorder()->select('drivers.id'));

        $byStatus = DB::query()
            ->fromSub($counts, 'c')
            ->selectRaw(
                "case when c.total_docs - c.valid_docs - c.expiring_docs > 0 then 'danger' "
                ."when c.expiring_docs > 0 then 'warning' else 'compliant' end as compliance_status, count(*) as drivers"
            )
            ->groupBy('compliance_status')
            ->pluck('drivers', 'compliance_status');

        $summary = ['compliant' => 0, 'warning' => 0, 'danger' => 0];
        foreach ($byStatus as $status => $count) {
            $summary[$status] = (int) $count;
        }

        return ['total' => array_sum($summary)] + $summary;
    }

    /**
     * Required driver document types, correlated to the outer `drivers` row.
     */
    private function requiredTypes(): QueryBuilder
    {
        return DB::table('document_types')
            ->where('document_types.module', 'driver')
            ->where('document_types.status', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('company_document_type')
                ->whereColumn('company_document_type.document_type_id', 'document_types.id')
                ->whereColumn('company_document_type.company_id', 'drivers.company_id'));
    }

    /**
     * Required types joined to the driver's (oldest) document of that type.
     */
    private function requiredTypesWithDocument(): QueryBuilder
    {
        return $this->requiredTypes()
            ->join('driver_compliance_documents as dcd', function ($join) {
                $join->on('dcd.document_type_id', '=', 'document_types.id')
                    ->on('dcd.driver_id', '=', 'drivers.id')
                    ->whereRaw('dcd.id = (select min(d2.id) from driver_compliance_documents as d2 where d2.driver_id = drivers.id and d2.document_type_id = document_types.id)');
            });
    }
}
