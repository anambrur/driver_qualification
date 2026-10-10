<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Models\ServiceLog;
use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Traits\CompanyFilterTrait;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    use CompanyFilterTrait;

    public function index()
    {
        $user         = Auth::user();
        $isSuperAdmin = $user->hasRole('super-admin');

        // Company context (for company dashboards & share links)
        $company = $user->loadMissing('company')->company;

        // Drivers (company-filtered for non-super-admin)
        $driversQuery = Driver::query();
        $driversQuery = $this->applyCompanyFilter($driversQuery);

        // Drafts are hidden from tenants' driver list, so keep the numbers consistent
        if (! $isSuperAdmin) {
            $driversQuery->where('status', '!=', 'draft');
        }

        // One query per table: conditional aggregates instead of a COUNT per stat
        $driverCounts = $driversQuery->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status IN ('pending', 'submitted', 'under_review') THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status IN ('inactive', 'rejected') THEN 1 ELSE 0 END) as inactive")
            ->selectRaw("SUM(CASE WHEN source = 'public_application' AND created_at >= ? THEN 1 ELSE 0 END) as new_applicants", [now()->subDays(7)])
            ->first();

        $totalDrivers        = (int) $driverCounts->total;
        $activeDrivers       = (int) $driverCounts->active;
        $pendingDrivers      = (int) $driverCounts->pending;
        $inactiveDrivers     = (int) $driverCounts->inactive;
        $newApplicants7Days  = (int) $driverCounts->new_applicants;

        // Fleet (vehicles & trailers)
        $vehiclesQuery = Vehicle::query();
        $vehiclesQuery = $this->applyCompanyFilter($vehiclesQuery);

        $trailersQuery = Trailer::query();
        $trailersQuery = $this->applyCompanyFilter($trailersQuery);

        $totalVehicles = $vehiclesQuery->count();
        $totalTrailers = $trailersQuery->count();
        $totalUnits    = $totalVehicles + $totalTrailers;

        // Compliance snapshots (overdue / due soon documents)
        $driverDocsQuery = DriverComplianceDocument::query()->whereHas('driver', function ($q) {
            $this->applyCompanyFilter($q);
        });

        $vehicleDocsQuery = VehicleDocument::query()->whereHas('vehicle', function ($q) {
            $this->applyCompanyFilter($q);
        });

        $trailerDocsQuery = TrailerDocument::query()->whereHas('trailer', function ($q) {
            $this->applyCompanyFilter($q);
        });

        $now = now();

        [$driverOverdue, $driverDueSoon]   = $this->expiryCounts($driverDocsQuery, $now);
        [$vehicleOverdue, $vehicleDueSoon] = $this->expiryCounts($vehicleDocsQuery, $now);
        [$trailerOverdue, $trailerDueSoon] = $this->expiryCounts($trailerDocsQuery, $now);

        $fleetOverdue = $vehicleOverdue + $trailerOverdue;
        $fleetDueSoon = $vehicleDueSoon + $trailerDueSoon;

        // Maintenance overview
        $serviceLogsQuery = ServiceLog::query();
        $serviceLogsQuery = $this->applyCompanyFilter($serviceLogsQuery);

        $serviceCounts = $serviceLogsQuery->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN service_date >= ? THEN 1 ELSE 0 END) as recent', [now()->subDays(30)])
            ->first();

        $totalServices      = (int) $serviceCounts->total;
        $recentServices30   = (int) $serviceCounts->recent;

        // Company-wide metrics (admin only)
        $companyStats = null;
        if ($isSuperAdmin) {
            $companyCounts = Company::query()->toBase()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
                ->first();

            $totalCompanies  = (int) $companyCounts->total;
            $activeCompanies = (int) $companyCounts->active;

            $companyStats = [
                'total_companies'  => $totalCompanies,
                'active_companies' => $activeCompanies,
                'inactive_companies' => $totalCompanies - $activeCompanies,
            ];
        }

        return view('dashboard', [
            'isSuperAdmin' => $isSuperAdmin,
            'company'      => $company,
            'driverStats'  => [
                'total'          => $totalDrivers,
                'active'         => $activeDrivers,
                'pending'        => $pendingDrivers,
                'inactive'       => $inactiveDrivers,
                'new_applicants' => $newApplicants7Days,
            ],
            'fleetStats'   => [
                'units'    => $totalUnits,
                'vehicles' => $totalVehicles,
                'trailers' => $totalTrailers,
            ],
            'driverComplianceStats' => [
                'overdue' => $driverOverdue,
                'due_soon' => $driverDueSoon,
            ],
            'fleetComplianceStats'  => [
                'overdue' => $fleetOverdue,
                'due_soon' => $fleetDueSoon,
            ],
            'maintenanceStats'      => [
                'total_services'    => $totalServices,
                'recent_30_days'    => $recentServices30,
            ],
            'companyStats'          => $companyStats,
        ]);
    }

    /**
     * Overdue and due-within-30-days document counts in a single query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $docsQuery
     * @return array{0: int, 1: int}
     */
    private function expiryCounts($docsQuery, \Illuminate\Support\Carbon $now): array
    {
        $counts = $docsQuery->toBase()
            ->whereNotNull('expiry_date')
            ->selectRaw('SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END) as overdue', [$now])
            ->selectRaw('SUM(CASE WHEN expiry_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_soon', [$now, $now->copy()->addDays(30)])
            ->first();

        return [(int) $counts->overdue, (int) $counts->due_soon];
    }
}
