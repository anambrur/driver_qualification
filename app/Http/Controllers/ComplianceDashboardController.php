<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Services\Compliance\FleetComplianceService;
use App\Traits\CompanyFilterTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

class ComplianceDashboardController extends Controller
{
    use CompanyFilterTrait;

    public function __construct(
        private readonly FleetComplianceService $fleetCompliance
    ) {}

    /** Vehicles / trailers per page on the dashboard (PO-01). */
    private const PER_PAGE = 25;

    public function fleet()
    {
        $vehiclesQuery = Vehicle::query();
        $trailersQuery = Trailer::query();

        // Summary cards cover every asset, computed in SQL (PO-01)
        $vehicleSummary = $this->fleetCompliance->summary($vehiclesQuery, 'vehicle');
        $trailerSummary = $this->fleetCompliance->summary($trailersQuery, 'trailer');

        // Only the current page of each list is loaded with its documents; each list has its
        // own page parameter and its links keep the other list's page.
        $vehiclesPage = $vehiclesQuery->clone()
            ->with(['assetGroups.driver', 'documents.documentType', 'company'])
            ->orderBy('unit_no')->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'vehicles_page')
            ->withQueryString();

        $trailersPage = $trailersQuery->clone()
            ->with(['assetGroups.driver', 'documents.documentType', 'company'])
            ->orderBy('unit_no')->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'trailers_page')
            ->withQueryString();

        // Get document types (no company filtering needed as these are global)
        $vehicleDocumentTypes = DocumentType::where('module', 'vehicle')
            ->where('status', true)
            ->get();

        $trailersDocumentTypes = DocumentType::where('module', 'trailer')
            ->where('status', true)
            ->get();

        // Each company may have switched some global types off for itself
        $disabledTypeIds = DocumentType::disabledIdsByCompany(
            $vehiclesPage->getCollection()->pluck('company_id')
                ->merge($trailersPage->getCollection()->pluck('company_id'))->unique()->values()->all()
        );

        $vehicles = $vehiclesPage->through(function (Vehicle $vehicle) use ($vehicleDocumentTypes, $disabledTypeIds): array {
            $complianceData = $this->calculateCompliance(
                $vehicle,
                $vehicleDocumentTypes->whereNotIn('id', $disabledTypeIds[$vehicle->company_id] ?? [])->values(),
                'vehicle'
            );

            return [
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
                'compliance_percentage' => $complianceData['percentage'].'%',
                'compliant_docs' => $complianceData['compliant_docs'],
                'total_docs' => $complianceData['total_docs'],
                'missing_documents' => $complianceData['missing_documents'],
                'expiring_documents' => $complianceData['expiring_documents'],
                'document_details' => $complianceData['document_details'],
            ];
        });

        $trailers = $trailersPage->through(function (Trailer $trailer) use ($trailersDocumentTypes, $disabledTypeIds): array {
            $complianceData = $this->calculateCompliance(
                $trailer,
                $trailersDocumentTypes->whereNotIn('id', $disabledTypeIds[$trailer->company_id] ?? [])->values(),
                'trailer'
            );

            return [
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
                'compliance_percentage' => $complianceData['percentage'].'%',
                'compliant_docs' => $complianceData['compliant_docs'],
                'total_docs' => $complianceData['total_docs'],
                'missing_documents' => $complianceData['missing_documents'],
                'expiring_documents' => $complianceData['expiring_documents'],
                'document_details' => $complianceData['document_details'],
            ];
        });

        // Calculate overall metrics
        $totalVehicles = $vehicleSummary['total'];
        $totalTrailers = $trailerSummary['total'];
        $totalAssets = $totalVehicles + $totalTrailers;

        $totalCompliant = $vehicleSummary['compliant'] + $trailerSummary['compliant'];
        // Warning = only expiring documents; Critical = missing/expired ones (PO-22)
        $totalWarning = $vehicleSummary['warning'] + $trailerSummary['warning'];
        $totalCritical = $vehicleSummary['danger'] + $trailerSummary['danger'];

        $overallCompliance = $totalAssets > 0
            ? round(($totalCompliant / $totalAssets) * 100, 1)
            : 0;

        $vehicleCompliance = $totalVehicles > 0
            ? round(($vehicleSummary['compliant'] / $totalVehicles) * 100, 1)
            : 0;

        $trailerCompliance = $totalTrailers > 0
            ? round(($trailerSummary['compliant'] / $totalTrailers) * 100, 1)
            : 0;

        $companies = $this->getCompaniesForUser();

        return view('admin.compliance.fleet', [
            'vehicles' => $vehicles,
            'trailers' => $trailers,
            'vehicleDocumentTypes' => $vehicleDocumentTypes,
            'trailersDocumentTypes' => $trailersDocumentTypes,
            'totalVehicles' => $totalVehicles,
            'totalTrailers' => $totalTrailers,
            'totalCompliant' => $totalCompliant,
            'totalWarning' => $totalWarning,
            'totalCritical' => $totalCritical,
            'vehiclesNeedingAttention' => $vehicleSummary['warning'] + $vehicleSummary['danger'],
            'trailersNeedingAttention' => $trailerSummary['warning'] + $trailerSummary['danger'],
            'overallCompliance' => $overallCompliance,
            'vehicleCompliance' => $vehicleCompliance,
            'trailerCompliance' => $trailerCompliance,
            'companies' => $companies,
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

        // The oldest document of each type counts, as in FleetComplianceService.
        $documents = $asset->documents->sortBy('id');

        foreach ($documentTypes as $docType) {
            // Use eager-loaded collection to avoid N+1 queries
            $document = $documents->firstWhere('document_type_id', $docType->id);

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
                $docStatus['document_id'] = $document->id;
                $docStatus['file_path'] = $document->file_path;
                $docStatus['description'] = $document->description;

                if (! $document->expiry_date) {
                    // Present with no expiry treated as valid
                    $docStatus['status'] = 'valid';
                    $compliantDocs++;
                } else {
                    $expiryDate = Carbon::parse($document->expiry_date);
                    $today = Carbon::today();
                    // Whole days: Carbon 3 returns a float, which rendered as "10.0 days" (PO-22)
                    $daysUntilExpiry = (int) $today->diffInDays($expiryDate, false);

                    $docStatus['expiry_date'] = $document->expiry_date;
                    $docStatus['days_until_expiry'] = $daysUntilExpiry;

                    if ($expiryDate->isFuture()) {
                        if ($daysUntilExpiry <= 30) {
                            // Expiring soon (within 30 days)
                            $docStatus['status'] = 'expiring';
                            $expiringDocs[] = $docType->name.' (expires in '.$daysUntilExpiry.' days)';
                        } else {
                            // Valid and not expiring soon
                            $docStatus['status'] = 'valid';
                            $compliantDocs++;
                        }
                    } else {
                        // Expired
                        $docStatus['status'] = 'expired';
                        $missingDocs[] = $docType->name.' (expired)';
                    }
                }
            } else {
                // Document missing
                $missingDocs[] = $docType->name;
            }

            $documentDetails[] = $docStatus;
        }

        // Count only fully valid (non-expiring) docs for the percentage display
        $percentage = $totalDocs > 0 ? round(($compliantDocs / $totalDocs) * 100, 1) : 0;

        // Missing/expired = danger; only expiring soon = warning; all valid = compliant
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
     * Get vehicle details for modal
     */
    public function getVehicleDetails($id)
    {
        try {
            $vehicle = Vehicle::with(['assetGroups.driver', 'documents.documentType', 'company'])
                ->findOrFail($id);

            // Check if user has access to this vehicle
            Gate::authorize('view', $vehicle);

            $vehicleDocumentTypes = DocumentType::where('module', 'vehicle')
                ->enabledForCompany($vehicle->company_id)
                ->get();

            $complianceData = $this->calculateCompliance($vehicle, $vehicleDocumentTypes, 'vehicle');

            // Get all documents with their details
            $documents = $vehicle->documents->map(function ($doc) {
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
                    'compliance_percentage' => $complianceData['percentage'].'%',
                    'compliant_docs' => $complianceData['compliant_docs'],
                    'total_docs' => $complianceData['total_docs'],
                    'missing_documents' => $complianceData['missing_documents'],
                    'expiring_documents' => $complianceData['expiring_documents'],
                    'document_details' => $complianceData['document_details'],
                    'documents' => $documents,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found or access denied',
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
            Gate::authorize('view', $trailer);

            $trailerDocumentTypes = DocumentType::where('module', 'trailer')
                ->enabledForCompany($trailer->company_id)
                ->get();

            $complianceData = $this->calculateCompliance($trailer, $trailerDocumentTypes, 'trailer');

            // Get all documents with their details
            $documents = $trailer->documents->map(function ($doc) {
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
                    'compliance_percentage' => $complianceData['percentage'].'%',
                    'compliant_docs' => $complianceData['compliant_docs'],
                    'total_docs' => $complianceData['total_docs'],
                    'missing_documents' => $complianceData['missing_documents'],
                    'expiring_documents' => $complianceData['expiring_documents'],
                    'document_details' => $complianceData['document_details'],
                    'documents' => $documents,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trailer not found or access denied',
            ], 404);
        }
    }
}
