<?php

namespace App\Services\Compliance;

use App\Models\Trailer;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Fleet (vehicle / trailer) compliance counts computed in SQL, with the same rules as
 * ComplianceDashboardController::calculateCompliance(), so the dashboard can paginate the lists
 * and still count its summary cards over every asset (PO-01).
 */
class FleetComplianceService
{
    /** A document expiring within this many days is "expiring", not "valid". */
    private const EXPIRING_WITHIN_DAYS = 30;

    /** Asset table, document table and document foreign key per asset type. */
    private const TABLES = [
        'vehicle' => ['vehicles', 'vehicle_documents', 'vehicle_id'],
        'trailer' => ['trailers', 'trailer_documents', 'trailer_id'],
    ];

    /**
     * Number of assets per compliance status, computed in SQL over the whole query.
     *
     * @param  Builder<Vehicle>|Builder<Trailer>  $assets
     * @return array{total: int, compliant: int, warning: int, danger: int}
     */
    public function summary(Builder $assets, string $assetType): array
    {
        [$table] = self::TABLES[$assetType];

        $counts = $this->withComplianceCounts($assets->clone()->reorder()->select($table.'.id'), $assetType);

        $byStatus = DB::query()
            ->fromSub($counts, 'c')
            ->selectRaw(
                "case when c.total_docs - c.valid_docs - c.expiring_docs > 0 then 'danger' "
                ."when c.expiring_docs > 0 then 'warning' else 'compliant' end as compliance_status, count(*) as assets"
            )
            ->groupBy('compliance_status')
            ->pluck('assets', 'compliance_status');

        $summary = ['compliant' => 0, 'warning' => 0, 'danger' => 0];
        foreach ($byStatus as $status => $count) {
            $summary[$status] = (int) $count;
        }

        return ['total' => array_sum($summary)] + $summary;
    }

    /**
     * Add total_docs, valid_docs and expiring_docs to an asset query: required = active types of
     * the asset's module its company hasn't switched off; expired = expiry today or earlier;
     * expiring = within 30 days.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $assets
     * @return TBuilder
     */
    public function withComplianceCounts(Builder $assets, string $assetType): Builder
    {
        // Dates are compared as Y-m-d strings with < / >=, which works for both a MySQL DATE
        // and the "Y-m-d 00:00:00" text SQLite stores.
        $tomorrow = Carbon::today()->addDay()->toDateString();
        $validFrom = Carbon::today()->addDays(self::EXPIRING_WITHIN_DAYS + 1)->toDateString();

        return $assets->addSelect([
            'total_docs' => $this->requiredTypes($assetType)->selectRaw('count(*)'),
            'valid_docs' => $this->requiredTypesWithDocument($assetType)->selectRaw('count(*)')
                ->where(fn ($q) => $q->whereNull('ad.expiry_date')->orWhere('ad.expiry_date', '>=', $validFrom)),
            'expiring_docs' => $this->requiredTypesWithDocument($assetType)->selectRaw('count(*)')
                ->where('ad.expiry_date', '>=', $tomorrow)
                ->where('ad.expiry_date', '<', $validFrom),
        ]);
    }

    /**
     * Required document types, correlated to the outer asset row.
     */
    private function requiredTypes(string $assetType): QueryBuilder
    {
        [$table] = self::TABLES[$assetType];

        return DB::table('document_types')
            ->where('document_types.module', $assetType)
            ->where('document_types.status', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('company_document_type')
                ->whereColumn('company_document_type.document_type_id', 'document_types.id')
                ->whereColumn('company_document_type.company_id', $table.'.company_id'));
    }

    /**
     * Required types joined to the asset's (oldest) document of that type.
     */
    private function requiredTypesWithDocument(string $assetType): QueryBuilder
    {
        [$table, $documents, $foreignKey] = self::TABLES[$assetType];

        return $this->requiredTypes($assetType)
            ->join($documents.' as ad', function ($join) use ($table, $documents, $foreignKey) {
                $join->on('ad.document_type_id', '=', 'document_types.id')
                    ->on('ad.'.$foreignKey, '=', $table.'.id')
                    ->whereRaw("ad.id = (select min(d2.id) from {$documents} as d2 where d2.{$foreignKey} = {$table}.id and d2.document_type_id = document_types.id)");
            });
    }
}
