<?php

namespace App\Http\Controllers;

use App\Models\Trailer;
use App\Models\Vehicle;
use App\Models\DocumentType;
use App\Traits\CompanyFilterTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ComplianceDashboardController extends Controller
{
    use CompanyFilterTrait;

    public function fleet()
    {
        // Get vehicles with company filtering
        $vehiclesQuery = Vehicle::with(['assetGroups.driver', 'documents.documentType']);
        $vehiclesQuery = $this->applyCompanyFilter($vehiclesQuery);
        $vehicles = $vehiclesQuery->orderBy('unit_no')->get();

        // Get trailers with company filtering
        $trailersQuery = Trailer::with(['assetGroups.driver', 'documents.documentType']);
        $trailersQuery = $this->applyCompanyFilter($trailersQuery);
        $trailers = $trailersQuery->orderBy('unit_no')->get();

        // Get document types (no company filtering needed as these are global)
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
                'company_id' => $vehicle->company_id,
                'company_name' => $vehicle->company?->company_name,
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
                'company_id' => $trailer->company_id,
                'company_name' => $trailer->company?->company_name,
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

        // Get companies for filter (if super-admin)
        $companies = $this->getCompaniesForUser();

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
            'companies' => $companies,
            'isSuperAdmin' => Auth::user()->hasRole('super-admin'),
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
                'document_id' => null,
                'file_path' => null,
                'description' => null,
            ];

            if ($document) {
                $expiryDate = Carbon::parse($document->expiry_date);
                $today = Carbon::today();
                $daysUntilExpiry = $today->diffInDays($expiryDate, false);

                $docStatus['expiry_date'] = $document->expiry_date;
                $docStatus['days_until_expiry'] = $daysUntilExpiry;
                $docStatus['document_id'] = $document->id;
                $docStatus['file_path'] = $document->file_path;
                $docStatus['description'] = $document->description;

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
            $vehicle = Vehicle::with(['assetGroups.driver', 'documents.documentType', 'company'])
                ->findOrFail($id);

            // Check if user has access to this vehicle
            $this->authorizeCompanyAccess($vehicle, 'You do not have permission to view this vehicle.');

            $vehicleDocumentTypes = DocumentType::where('module', 'vehicle')
                ->where('status', true)
                ->get();

            $complianceData = $this->calculateCompliance($vehicle, $vehicleDocumentTypes, 'vehicle');

            // Get all documents with their details
            $documents = $vehicle->documents()->with('documentType')->get()->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type_name' => $doc->documentType->name,
                    'expiry_date' => $doc->expiry_date,
                    'description' => $doc->description,
                    'file_path' => $doc->file_path,
                    'status' => $doc->status,
                    'days_until_expiry' => $doc->days_until_expiry,
                ];
            });

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
                    'company_id' => $vehicle->company_id,
                    'company_name' => $vehicle->company?->company_name,
                    'compliance_status' => $complianceData['status'],
                    'compliance_percentage' => $complianceData['percentage'] . '%',
                    'compliant_docs' => $complianceData['compliant_docs'],
                    'total_docs' => $complianceData['total_docs'],
                    'missing_documents' => $complianceData['missing_documents'],
                    'expiring_documents' => $complianceData['expiring_documents'],
                    'document_details' => $complianceData['document_details'],
                    'documents' => $documents,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found or access denied'
            ], 404);
        }
    }

    /**
     * Get trailer details for modal
     */
    public function getTrailerDetails($id)
    {
        try {
            $trailer = Trailer::with(['assetGroups.driver', 'documents.documentType', 'company'])
                ->findOrFail($id);

            // Check if user has access to this trailer
            $this->authorizeCompanyAccess($trailer, 'You do not have permission to view this trailer.');

            $trailerDocumentTypes = DocumentType::where('module', 'trailer')
                ->where('status', true)
                ->get();

            $complianceData = $this->calculateCompliance($trailer, $trailerDocumentTypes, 'trailer');

            // Get all documents with their details
            $documents = $trailer->documents()->with('documentType')->get()->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type_name' => $doc->documentType->name,
                    'expiry_date' => $doc->expiry_date,
                    'description' => $doc->description,
                    'file_path' => $doc->file_path,
                    'status' => $doc->status,
                    'days_until_expiry' => $doc->days_until_expiry,
                ];
            });

            return response()->json([
                'success' => true,
                'trailer' => [
                    'id' => $trailer->id,
                    'unit_no' => $trailer->unit_no,
                    'make' => $trailer->make,
                    'model' => $trailer->model,
                    'year' => $trailer->year,
                    'vin' => $trailer->vin,
                    'company_id' => $trailer->company_id,
                    'company_name' => $trailer->company?->company_name,
                    'compliance_status' => $complianceData['status'],
                    'compliance_percentage' => $complianceData['percentage'] . '%',
                    'compliant_docs' => $complianceData['compliant_docs'],
                    'total_docs' => $complianceData['total_docs'],
                    'missing_documents' => $complianceData['missing_documents'],
                    'expiring_documents' => $complianceData['expiring_documents'],
                    'document_details' => $complianceData['document_details'],
                    'documents' => $documents,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trailer not found or access denied'
            ], 404);
        }
    }

    /**
     * Get compliance summary by company (for super-admin)
     */
    public function getCompanySummary()
    {
        if (!Auth::user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $companies = \App\Models\Company::where('status', 'active')->get();
            $summary = [];

            foreach ($companies as $company) {
                $vehicles = Vehicle::where('company_id', $company->id)->count();
                $trailers = Trailer::where('company_id', $company->id)->count();

                $summary[] = [
                    'company_id' => $company->id,
                    'company_name' => $company->company_name,
                    'vehicles' => $vehicles,
                    'trailers' => $trailers,
                    'total_assets' => $vehicles + $trailers,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch company summary'
            ], 500);
        }
    }
}
