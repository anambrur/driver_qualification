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
use Illuminate\Http\Request;
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

        $totalDrivers        = (clone $driversQuery)->count();
        $activeDrivers       = (clone $driversQuery)->where('status', 'active')->count();
        $pendingDrivers      = (clone $driversQuery)->whereIn('status', ['pending', 'submitted', 'under_review'])->count();
        $inactiveDrivers     = (clone $driversQuery)->whereIn('status', ['inactive', 'rejected'])->count();
        $newApplicants7Days  = (clone $driversQuery)
            ->where('source', 'public_application')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Fleet (vehicles & trailers)
        $vehiclesQuery = Vehicle::query();
        $vehiclesQuery = $this->applyCompanyFilter($vehiclesQuery);

        $trailersQuery = Trailer::query();
        $trailersQuery = $this->applyCompanyFilter($trailersQuery);

        $totalVehicles = (clone $vehiclesQuery)->count();
        $totalTrailers = (clone $trailersQuery)->count();
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

        $driverOverdue   = (clone $driverDocsQuery)->whereNotNull('expiry_date')->where('expiry_date', '<', $now)->count();
        $driverDueSoon   = (clone $driverDocsQuery)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$now, $now->copy()->addDays(30)])
            ->count();

        $vehicleOverdue  = (clone $vehicleDocsQuery)->whereNotNull('expiry_date')->where('expiry_date', '<', $now)->count();
        $vehicleDueSoon  = (clone $vehicleDocsQuery)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$now, $now->copy()->addDays(30)])
            ->count();

        $trailerOverdue  = (clone $trailerDocsQuery)->whereNotNull('expiry_date')->where('expiry_date', '<', $now)->count();
        $trailerDueSoon  = (clone $trailerDocsQuery)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$now, $now->copy()->addDays(30)])
            ->count();

        $fleetOverdue = $vehicleOverdue + $trailerOverdue;
        $fleetDueSoon = $vehicleDueSoon + $trailerDueSoon;

        // Maintenance overview
        $serviceLogsQuery = ServiceLog::query();
        $serviceLogsQuery = $this->applyCompanyFilter($serviceLogsQuery);

        $totalServices      = (clone $serviceLogsQuery)->count();
        $recentServices30   = (clone $serviceLogsQuery)->where('service_date', '>=', now()->subDays(30))->count();

        // Company-wide metrics (admin only)
        $companyStats = null;
        if ($isSuperAdmin) {
            $totalCompanies  = Company::count();
            $activeCompanies = Company::where('status', 'active')->count();

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

    public function profit(Request $request)
    {
        return view('admin.profit');
    }
}
