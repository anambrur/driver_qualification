<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DocumentType;
use App\Traits\CompanyFilterTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DriverComplianceDashboardController extends Controller
{
    use CompanyFilterTrait;

    public function index()
    {
        // Get drivers with company filtering and eager load documents
        $driversQuery = Driver::with(['documents.documentType', 'company']);
        $driversQuery = $this->applyCompanyFilter($driversQuery);
        $drivers = $driversQuery->orderBy('first_name')->orderBy('last_name')->where('status', 'active')->get();

        // Get driver document types (global settings)
        $driverDocumentTypes = DocumentType::where('module', 'driver')
            ->where('status', true)
            ->get();

        // Process drivers compliance
        $processedDrivers = [];
        $compliantDrivers = 0;
        $warningDrivers = 0;
        $criticalDrivers = 0;

        foreach ($drivers as $driver) {
            $complianceData = $this->calculateCompliance($driver, $driverDocumentTypes);

            $processedDrivers[] = [
                'id' => $driver->id,
                'full_name' => $driver->first_name . ' ' . $driver->last_name,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'date_of_birth' => $driver->date_of_birth,
                'license_number' => $driver->license_number,
                'license_state' => $driver->license_state,
                'hire_date' => $driver->hire_date,
                'status' => $driver->status,
                'company_id' => $driver->company_id,
                'company_name' => $driver->company?->company_name,
                'compliance_status' => $complianceData['status'],
                'compliance_percentage' => $complianceData['percentage'] . '%',
                'compliant_docs' => $complianceData['compliant_docs'],
                'total_docs' => $complianceData['total_docs'],
                'missing_documents' => $complianceData['missing_documents'],
                'expiring_documents' => $complianceData['expiring_documents'],
                'document_details' => $complianceData['document_details'],
            ];

            if ($complianceData['status'] === 'compliant') {
                $compliantDrivers++;
            } elseif ($complianceData['status'] === 'warning') {
                $warningDrivers++;
            } elseif ($complianceData['status'] === 'danger') {
                $criticalDrivers++;
            }
        }

        // Calculate overall metrics
        $totalDrivers = count($processedDrivers);
        $totalCompliant = $compliantDrivers;
        $totalWarning = $warningDrivers;
        $totalCritical = $criticalDrivers;

        $overallCompliance = $totalDrivers > 0
            ? round((($compliantDrivers) / $totalDrivers) * 100, 1)
            : 0;

        // Get companies for filter (if super-admin)
        $companies = $this->getCompaniesForUser();

        return view('admin.compliance.drivers', [
            'drivers' => $processedDrivers,
            'driverDocumentTypes' => $driverDocumentTypes,
            'totalDrivers' => $totalDrivers,
            'totalCompliant' => $totalCompliant,
            'totalWarning' => $totalWarning,
            'totalCritical' => $totalCritical,
            'overallCompliance' => $overallCompliance,
            'companies' => $companies,
            'isSuperAdmin' => Auth::user()->hasRole('super-admin'),
        ]);
    }

    /**
     * Calculate compliance for a driver
     */
    private function calculateCompliance($driver, $documentTypes)
    {
        $totalDocs = $documentTypes->count();
        $compliantDocs = 0;
        $missingDocs = [];
        $expiringDocs = [];
        $documentDetails = [];

        foreach ($documentTypes as $docType) {
            // Use eager-loaded collection to avoid N+1 queries
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
            ];

            if ($document) {
                $docStatus['file_date'] = $document->file_date;
                $docStatus['expiry_date'] = $document->expiry_date;
                $docStatus['document_id'] = $document->id;
                $docStatus['file_path'] = $document->file_path;
                $docStatus['description'] = $document->description;

                if ($document->expiry_date) {
                    $expiryDate = Carbon::parse($document->expiry_date);
                    $today = Carbon::today();
                    $daysUntilExpiry = $today->diffInDays($expiryDate, false);

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
                    // Document has no expiry date (like certificates without expiry)
                    $docStatus['status'] = 'valid';
                    $compliantDocs++;
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
     * Get driver details for modal
     */
    public function getDriverDetails($id)
    {
        try {
            $driver = Driver::with(['documents.documentType', 'company'])
                ->findOrFail($id);

            // Check if user has access to this driver
            $this->authorizeCompanyAccess($driver, 'You do not have permission to view this driver.');

            $driverDocumentTypes = DocumentType::where('module', 'driver')
                ->where('status', true)
                ->get();

            $complianceData = $this->calculateCompliance($driver, $driverDocumentTypes);

            // Use already eager-loaded documents collection
            $documents = $driver->documents->map(function ($doc) {
                $daysUntilExpiry = null;
                if ($doc->expiry_date) {
                    $expiryDate = Carbon::parse($doc->expiry_date);
                    $today = Carbon::today();
                    $daysUntilExpiry = $today->diffInDays($expiryDate, false);
                }

                return [
                    'id' => $doc->id,
                    'type_name' => $doc->documentType?->name,
                    'file_date' => $doc->file_date,
                    'expiry_date' => $doc->expiry_date,
                    'description' => $doc->description,
                    'file_path' => $doc->file_path,
                    'days_until_expiry' => $daysUntilExpiry,
                ];
            });

            return response()->json([
                'success' => true,
                'driver' => [
                    'id' => $driver->id,
                    'full_name' => $driver->first_name . ' ' . $driver->last_name,
                    'first_name' => $driver->first_name,
                    'last_name' => $driver->last_name,
                    'email' => $driver->email,
                    'phone' => $driver->phone,
                    'date_of_birth' => $driver->date_of_birth,
                    'license_number' => $driver->license_number,
                    'license_state' => $driver->license_state,
                    'hire_date' => $driver->hire_date,
                    'status' => $driver->status,
                    'company_id' => $driver->company_id,
                    'company_name' => $driver->company?->company_name,
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
                'message' => 'Driver not found or access denied'
            ], 404);
        }
    }
}
