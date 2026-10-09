<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DocumentType;
use App\Services\Compliance\DriverComplianceService;
use App\Traits\CompanyFilterTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DriverComplianceDashboardController extends Controller
{
    use CompanyFilterTrait;

    public function __construct(
        private readonly DriverComplianceService $complianceService
    ) {}

    /** Drivers per page on the dashboard (DCMP-05). */
    private const PER_PAGE = 25;

    public function index()
    {
        // Active drivers with company filtering
        $driversQuery = $this->applyCompanyFilter(Driver::query())->where('status', 'active');

        // Summary cards cover every driver, computed in SQL (DCMP-05)
        $summary = $this->complianceService->summary($driversQuery);

        // Only the current page is loaded with its documents
        $page = $driversQuery->clone()
            ->with(['documents.documentType', 'company'])
            ->orderBy('first_name')->orderBy('last_name')->orderBy('id')
            ->paginate(self::PER_PAGE);

        // Get driver document types (global settings)
        $driverDocumentTypes = DocumentType::where('module', 'driver')
            ->where('status', true)
            ->get();
        $disabledTypeIds = DocumentType::disabledIdsByCompany($page->getCollection()->pluck('company_id')->unique()->values()->all());

        // Process drivers compliance
        // Laravel's through() is typed with @phpstan-this-out static<…>, which PHPStan can't resolve.
        // @phpstan-ignore argument.unresolvableType
        $drivers = $page->through(function (Driver $driver) use ($driverDocumentTypes, $disabledTypeIds): array {
            $complianceData = $this->complianceService->calculateCompliance(
                $driver,
                $driverDocumentTypes->whereNotIn('id', $disabledTypeIds[$driver->company_id] ?? [])->values()
            );

            return [
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
        });

        // Calculate overall metrics
        $totalDrivers = $summary['total'];

        $overallCompliance = $totalDrivers > 0
            ? round(($summary['compliant'] / $totalDrivers) * 100, 1)
            : 0;

        // Get companies for filter (if super-admin)
        $companies = $this->getCompaniesForUser();

        return view('admin.compliance.drivers', [
            'drivers' => $drivers,
            'driverDocumentTypes' => $driverDocumentTypes,
            'totalDrivers' => $totalDrivers,
            'totalCompliant' => $summary['compliant'],
            'totalWarning' => $summary['warning'],
            'totalCritical' => $summary['danger'],
            'overallCompliance' => $overallCompliance,
            'companies' => $companies,
            'isSuperAdmin' => Auth::user()->hasRole('super-admin'),
        ]);
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

            $complianceData = $this->complianceService->forDriver($driver);

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
