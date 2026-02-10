<?php

namespace App\Http\Controllers;

use App\Models\Trailer;
use App\Models\Vehicle;
use App\Models\DocumentType;
use App\Models\VehicleDocument;
use Illuminate\Http\Request;

class ComplianceDashboardController extends Controller
{
    public function fleet()
    {
        $vehicles = Vehicle::with(['assetGroups.driver', 'documents.documentType'])
            ->orderBy('id', 'desc')
            ->get();

        $trailers = Trailer::with(['assetGroups.driver', 'documents.documentType'])
            ->orderBy('id', 'desc')
            ->get();

        $vehicleDocumentTypes = DocumentType::where('module', 'vehicle')->get();
        $trailersDocumentTypes = DocumentType::where('module', 'trailer')->get();

        // Calculate compliance metrics
        $compliantVehicles = 0;
        $warningVehicles = 0;
        $compliantTrailers = 0;
        $warningTrailers = 0;

        foreach ($vehicles as $vehicle) {
            $vehicle->compliance_data = $this->calculateCompliance($vehicle, $vehicleDocumentTypes);
            if ($vehicle->compliance_data['status'] === 'compliant') $compliantVehicles++;
            if ($vehicle->compliance_data['status'] === 'warning') $warningVehicles++;
        }

        foreach ($trailers as $trailer) {
            $trailer->compliance_data = $this->calculateCompliance($trailer, $trailersDocumentTypes);
            if ($trailer->compliance_data['status'] === 'compliant') $compliantTrailers++;
            if ($trailer->compliance_data['status'] === 'warning') $warningTrailers++;
        }

        $totalCompliant = $compliantVehicles + $compliantTrailers;
        $totalWarning = $warningVehicles + $warningTrailers;
        $overallCompliance = ($vehicles->count() + $trailers->count()) > 0
            ? round(($totalCompliant / ($vehicles->count() + $trailers->count())) * 100, 1)
            : 0;

        return view('admin.compliance.fleet', compact(
            'vehicles',
            'trailers',
            'vehicleDocumentTypes',
            'trailersDocumentTypes',
            'totalCompliant',
            'totalWarning',
            'overallCompliance'
        ));
    }

    private function calculateCompliance($asset, $documentTypes)
    {
        $totalDocs = $documentTypes->count();
        $compliantDocs = 0;
        $missingDocs = [];

        foreach ($documentTypes as $docType) {
            $hasDoc = $asset->documents()
                ->where('document_type_id', $docType->id)
                ->where(function ($q) {
                    $q->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>=', now());
                })
                ->exists();

            if ($hasDoc) {
                $compliantDocs++;
            } else {
                $missingDocs[] = $docType->name;
            }
        }

        $percentage = $totalDocs > 0 ? round(($compliantDocs / $totalDocs) * 100, 1) : 0;

        return [
            'total_docs' => $totalDocs,
            'compliant_docs' => $compliantDocs,
            'percentage' => $percentage,
            'missing_documents' => $missingDocs,
            'status' => $percentage >= 100 ? 'compliant' : ($percentage >= 75 ? 'warning' : 'danger')
        ];
    }
}
