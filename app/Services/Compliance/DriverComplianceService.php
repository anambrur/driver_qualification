<?php

namespace App\Services\Compliance;

use App\Models\DocumentType;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DriverComplianceService
{
    /**
     * Calculate compliance for a driver using active driver document types.
     */
    public function forDriver(Driver $driver): array
    {
        if (!$driver->relationLoaded('documents')) {
            $driver->load('documents.documentType');
        }

        $documentTypes = DocumentType::where('module', 'driver')
            ->where('status', true)
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

        foreach ($documentTypes as $docType) {
            $document = $driver->documents->firstWhere('document_type_id', $docType->id);

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

        $status = 'compliant';
        if (count($missingDocs) > 0 || $percentage < 100) {
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
}
