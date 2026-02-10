<?php

namespace App\Http\Controllers;

use App\Models\Trailer;
use App\Models\Vehicle;
use App\Models\DocumentType;
use App\Models\VehicleDocument;
use App\Models\TrailerDocument;
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
        $trailerDocumentTypes = DocumentType::where('module', 'trailer')->get();

        // Calculate compliance for each vehicle
        $warningVehicles = 0;
        $dangerVehicles = 0;
        $compliantVehicles = 0;

        foreach ($vehicles as $vehicle) {
            $complianceData = $this->calculateCompliance($vehicle, $vehicleDocumentTypes);
            $vehicle->compliance_percentage = $complianceData['percentage'];
            $vehicle->compliant_docs = $complianceData['compliant_docs'];
            $vehicle->total_docs = $complianceData['total_docs'];
            $vehicle->missing_documents = $complianceData['missing_documents'];
            $vehicle->compliance_status = $complianceData['status'];

            if ($complianceData['status'] === 'warning') $warningVehicles++;
            elseif ($complianceData['status'] === 'danger') $dangerVehicles++;
            else $compliantVehicles++;
        }

        // Calculate compliance for each trailer
        $warningTrailers = 0;
        $dangerTrailers = 0;
        $compliantTrailers = 0;

        foreach ($trailers as $trailer) {
            $complianceData = $this->calculateCompliance($trailer, $trailerDocumentTypes);
            $trailer->compliance_percentage = $complianceData['percentage'];
            $trailer->compliant_docs = $complianceData['compliant_docs'];
            $trailer->total_docs = $complianceData['total_docs'];
            $trailer->missing_documents = $complianceData['missing_documents'];
            $trailer->compliance_status = $complianceData['status'];

            if ($complianceData['status'] === 'warning') $warningTrailers++;
            elseif ($complianceData['status'] === 'danger') $dangerTrailers++;
            else $compliantTrailers++;
        }

        $totalVehicles = $vehicles->count();
        $totalTrailers = $trailers->count();
        $totalCompliant = $compliantVehicles + $compliantTrailers;
        $totalWarning = $warningVehicles + $warningTrailers + $dangerVehicles + $dangerTrailers;

        // Calculate overall compliance percentage
        $totalAssets = $totalVehicles + $totalTrailers;
        if ($totalAssets > 0) {
            $overallCompliance = round(($totalCompliant / $totalAssets) * 100);
        } else {
            $overallCompliance = 0;
        }

        // Calculate vehicle compliance percentage
        if ($totalVehicles > 0) {
            $vehicleCompliance = round(($compliantVehicles / $totalVehicles) * 100);
        } else {
            $vehicleCompliance = 0;
        }

        // Calculate trailer compliance percentage
        if ($totalTrailers > 0) {
            $trailerCompliance = round(($compliantTrailers / $totalTrailers) * 100);
        } else {
            $trailerCompliance = 0;
        }

        return view('admin.compliance.fleet', compact(
            'vehicles',
            'trailers',
            'vehicleDocumentTypes',
            'trailerDocumentTypes',
            'totalVehicles',
            'totalTrailers',
            'totalCompliant',
            'totalWarning',
            'overallCompliance',
            'vehicleCompliance',
            'trailerCompliance',
            'warningVehicles',
            'warningTrailers',
            'compliantVehicles',
            'compliantTrailers'
        ));
    }

    private function calculateCompliance($asset, $documentTypes)
    {
        $totalDocs = $documentTypes->count();
        $compliantDocs = 0;
        $missingDocs = [];

        foreach ($documentTypes as $docType) {
            $hasValidDoc = false;

            // Check if asset has this document type with valid expiry date
            if ($asset->documents) {
                foreach ($asset->documents as $doc) {
                    if ($doc->document_type_id == $docType->id) {
                        // Check if expiry date is in future or null
                        if (is_null($doc->expiry_date) || $doc->expiry_date >= now()->toDateString()) {
                            $hasValidDoc = true;
                            break;
                        }
                    }
                }
            }

            if ($hasValidDoc) {
                $compliantDocs++;
            } else {
                $missingDocs[] = $docType->name;
            }
        }

        $percentage = $totalDocs > 0 ? round(($compliantDocs / $totalDocs) * 100) : 0;

        // Determine status based on percentage
        $status = 'danger'; // default
        if ($percentage == 100) {
            $status = 'compliant';
        } elseif ($percentage > 0) {
            $status = 'warning';
        }

        return [
            'total_docs' => $totalDocs,
            'compliant_docs' => $compliantDocs,
            'percentage' => $percentage,
            'missing_documents' => $missingDocs,
            'status' => $status
        ];
    }

    // Add method to check document status for each document type
    public function checkDocumentStatus($asset, $documentTypeId)
    {
        if (!$asset->documents) {
            return 'missing';
        }

        foreach ($asset->documents as $doc) {
            if ($doc->document_type_id == $documentTypeId) {
                if (is_null($doc->expiry_date) || $doc->expiry_date >= now()->toDateString()) {
                    return 'valid';
                } else {
                    return 'expired';
                }
            }
        }

        return 'missing';
    }
}
