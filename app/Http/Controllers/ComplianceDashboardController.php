<?php

namespace App\Http\Controllers;

use App\Models\Trailer;
use App\Models\Vehicle;
use App\Models\DocumentType;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ComplianceDashboardController extends Controller
{
    public function fleet()
    {
        // Get all vehicles with relationships
        $vehicles = Vehicle::with(['assetGroups.driver', 'documents.documentType'])
            ->orderBy('unit_no')
            ->get();

        // Get all trailers with relationships
        $trailers = Trailer::with(['assetGroups.driver', 'documents.documentType'])
            ->orderBy('unit_no')
            ->get();

        // Get document types
        $vehicleDocumentTypes = DocumentType::where('module', 'vehicle')
            ->where('status', true)
            ->get();
            
        $trailersDocumentTypes = DocumentType::where('module', 'trailer')
            ->where('status', true)
            ->get();

        // Process vehicles compliance
        $processedVehicles = [];
        $compliantVehicles = 0;
        $warningVehicles = 0;

        foreach ($vehicles as $vehicle) {
            $complianceData = $this->calculateCompliance($vehicle, $vehicleDocumentTypes, 'vehicle');
            
            $processedVehicles[] = [
                'id' => $vehicle->id,
                'unit_no' => $vehicle->unit_no,
                'vin' => $vehicle->vin,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'odometer' => $vehicle->odometer,
                'driver' => $vehicle->assetGroups->driver ?? null,
                'compliance_status' => $complianceData['status'],
                'compliance_percentage' => $complianceData['percentage'] . '%',
                'compliant_docs' => $complianceData['compliant_docs'],
                'total_docs' => $complianceData['total_docs'],
                'missing_documents' => $complianceData['missing_documents'],
                'expiring_documents' => $complianceData['expiring_documents'],
                'document_details' => $complianceData['document_details'],
            ];

            if ($complianceData['status'] === 'compliant') $compliantVehicles++;
            if ($complianceData['status'] === 'warning' || $complianceData['status'] === 'danger') $warningVehicles++;
        }

        // Process trailers compliance
        $processedTrailers = [];
        $compliantTrailers = 0;
        $warningTrailers = 0;

        foreach ($trailers as $trailer) {
            $complianceData = $this->calculateCompliance($trailer, $trailersDocumentTypes, 'trailer');
            
            $processedTrailers[] = [
                'id' => $trailer->id,
                'unit_no' => $trailer->unit_no,
                'vin' => $trailer->vin,
                'make' => $trailer->make,
                'model' => $trailer->model,
                'year' => $trailer->year,
                'driver' => $trailer->assetGroups->driver ?? null,
                'compliance_status' => $complianceData['status'],
                'compliance_percentage' => $complianceData['percentage'] . '%',
                'compliant_docs' => $complianceData['compliant_docs'],
                'total_docs' => $complianceData['total_docs'],
                'missing_documents' => $complianceData['missing_documents'],
                'expiring_documents' => $complianceData['expiring_documents'],
                'document_details' => $complianceData['document_details'],
            ];

            if ($complianceData['status'] === 'compliant') $compliantTrailers++;
            if ($complianceData['status'] === 'warning' || $complianceData['status'] === 'danger') $warningTrailers++;
        }

        // Calculate overall metrics
        $totalVehicles = count($processedVehicles);
        $totalTrailers = count($processedTrailers);
        $totalAssets = $totalVehicles + $totalTrailers;
        
        $totalCompliant = $compliantVehicles + $compliantTrailers;
        $totalWarning = $warningVehicles + $warningTrailers;
        
        $overallCompliance = $totalAssets > 0
            ? round(($totalCompliant / $totalAssets) * 100, 1)
            : 0;

        $vehicleCompliance = $totalVehicles > 0
            ? round(($compliantVehicles / $totalVehicles) * 100, 1)
            : 0;

        $trailerCompliance = $totalTrailers > 0
            ? round(($compliantTrailers / $totalTrailers) * 100, 1)
            : 0;

        return view('admin.compliance.fleet', [
            'vehicles' => $processedVehicles,
            'trailers' => $processedTrailers,
            'vehicleDocumentTypes' => $vehicleDocumentTypes,
            'trailersDocumentTypes' => $trailersDocumentTypes,
            'totalVehicles' => $totalVehicles,
            'totalTrailers' => $totalTrailers,
            'totalCompliant' => $totalCompliant,
            'totalWarning' => $totalWarning,
            'overallCompliance' => $overallCompliance,
            'vehicleCompliance' => $vehicleCompliance,
            'trailerCompliance' => $trailerCompliance,
        ]);
    }

    /**
     * Calculate compliance for a vehicle or trailer
     */
    private function calculateCompliance($asset, $documentTypes, $type = 'vehicle')
    {
        $totalDocs = $documentTypes->count();
        $compliantDocs = 0;
        $missingDocs = [];
        $expiringDocs = [];
        $documentDetails = [];

        foreach ($documentTypes as $docType) {
            // Find the document for this type
            $document = $asset->documents()
                ->where('document_type_id', $docType->id)
                ->first();

            $docStatus = [
                'type_id' => $docType->id,
                'type_name' => $docType->name,
                'status' => 'missing',
                'expiry_date' => null,
                'days_until_expiry' => null,
            ];

            if ($document) {
                $expiryDate = Carbon::parse($document->expiry_date);
                $today = Carbon::today();
                $daysUntilExpiry = $today->diffInDays($expiryDate, false);

                $docStatus['expiry_date'] = $document->expiry_date;
                $docStatus['days_until_expiry'] = $daysUntilExpiry;

                if ($expiryDate->isFuture()) {
                    if ($daysUntilExpiry <= 30) {
                        // Expiring soon (within 30 days)
                        $docStatus['status'] = 'expiring';
                        $expiringDocs[] = $docType->name . ' (expires in ' . $daysUntilExpiry . ' days)';
                    } else {
                        // Valid and not expiring soon
                        $docStatus['status'] = 'valid';
                        $compliantDocs++;
                    }
                } else {
                    // Expired
                    $docStatus['status'] = 'expired';
                    $missingDocs[] = $docType->name . ' (expired)';
                }
            } else {
                // Document missing
                $missingDocs[] = $docType->name;
            }

            $documentDetails[] = $docStatus;
        }

        $percentage = $totalDocs > 0 ? round(($compliantDocs / $totalDocs) * 100, 1) : 0;

        // Determine overall status
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

    /**
     * Get vehicle details for modal
     */
    public function getVehicleDetails($id)
    {
        try {
            $vehicle = Vehicle::with(['assetGroups.driver', 'documents.documentType'])
                ->findOrFail($id);

            $vehicleDocumentTypes = DocumentType::where('module', 'vehicle')
                ->where('status', true)
                ->get();

            $complianceData = $this->calculateCompliance($vehicle, $vehicleDocumentTypes, 'vehicle');

            return response()->json([
                'success' => true,
                'vehicle' => [
                    'id' => $vehicle->id,
                    'unit_no' => $vehicle->unit_no,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'vin' => $vehicle->vin,
                    'odometer' => $vehicle->odometer,
                    'compliance_status' => $complianceData['status'],
                    'compliance_percentage' => $complianceData['percentage'] . '%',
                    'compliant_docs' => $complianceData['compliant_docs'],
                    'total_docs' => $complianceData['total_docs'],
                    'missing_documents' => $complianceData['missing_documents'],
                    'expiring_documents' => $complianceData['expiring_documents'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }
    }

    /**
     * Get trailer details for modal
     */
    public function getTrailerDetails($id)
    {
        try {
            $trailer = Trailer::with(['assetGroups.driver', 'documents.documentType'])
                ->findOrFail($id);

            $trailerDocumentTypes = DocumentType::where('module', 'trailer')
                ->where('status', true)
                ->get();

            $complianceData = $this->calculateCompliance($trailer, $trailerDocumentTypes, 'trailer');

            return response()->json([
                'success' => true,
                'trailer' => [
                    'id' => $trailer->id,
                    'unit_no' => $trailer->unit_no,
                    'make' => $trailer->make,
                    'model' => $trailer->model,
                    'year' => $trailer->year,
                    'vin' => $trailer->vin,
                    'compliance_status' => $complianceData['status'],
                    'compliance_percentage' => $complianceData['percentage'] . '%',
                    'compliant_docs' => $complianceData['compliant_docs'],
                    'total_docs' => $complianceData['total_docs'],
                    'missing_documents' => $complianceData['missing_documents'],
                    'expiring_documents' => $complianceData['expiring_documents'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trailer not found'
            ], 404);
        }
    }
}