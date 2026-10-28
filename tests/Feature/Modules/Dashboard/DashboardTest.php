<?php

use App\Models\DriverComplianceDocument;
use App\Models\TrailerDocument;
use App\Models\User;
use App\Models\VehicleDocument;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\Route;
use Tests\Support\Actors;
use Tests\Support\Files;
use Tests\Support\Perf;

/**
 * Seed one company's worth of dashboard data: drivers in every status, vehicles, trailers,
 * overdue / due-soon / far-future documents and service logs.
 */
function seedDashboardData(User $owner, int $n): void
{
    $company = Actors::companyOf($owner);

    foreach (['active', 'pending', 'submitted', 'under_review', 'inactive', 'rejected', 'draft'] as $status) {
        DriverFactory::new()->forCompany($company)->status($status)->count($n)->create();
    }
    DriverFactory::new()->forCompany($company)->state(['source' => 'public_application'])->status('pending')->count($n)->create();

    $driverType = DocumentTypeFactory::new()->module('driver')->create();
    $vehicleType = DocumentTypeFactory::new()->module('vehicle')->create();
    $trailerType = DocumentTypeFactory::new()->module('trailer')->create();

    $drivers = DriverFactory::new()->forCompany($company)->count($n)->create();
    $vehicles = VehicleFactory::new()->forCompany($company)->count($n)->create();
    $trailers = TrailerFactory::new()->forCompany($company)->count($n)->create();

    $fileId = Files::stored($company->id)->id;
    app(TenantContext::class)->run($company->id, function () use ($drivers, $vehicles, $trailers, $driverType, $vehicleType, $trailerType, $fileId) {
        foreach ([now()->subDays(5), now()->addDays(10), now()->addDays(90)] as $expiry) {
            foreach ($drivers as $driver) {
                DriverComplianceDocument::create(['driver_id' => $driver->id, 'document_type_id' => $driverType->id, 'expiry_date' => $expiry->toDateString(), 'file_id' => $fileId]);
            }
            foreach ($vehicles as $vehicle) {
                VehicleDocument::create(['vehicle_id' => $vehicle->id, 'document_type_id' => $vehicleType->id, 'expiry_date' => $expiry->toDateString(), 'file_id' => $fileId]);
            }
            foreach ($trailers as $trailer) {
                TrailerDocument::create(['trailer_id' => $trailer->id, 'document_type_id' => $trailerType->id, 'expiry_date' => $expiry->toDateString(), 'file_id' => $fileId]);
            }
        }
    });

    foreach ($vehicles as $vehicle) {
        ServiceLogFactory::new()->forVehicle($vehicle)->create(['service_date' => now()->subDays(3)->toDateString()]);
        ServiceLogFactory::new()->forVehicle($vehicle)->create(['service_date' => now()->subDays(90)->toDateString()]);
    }
}

describe('DSH-01 → /profit stays removed', function () {
    it('returns 404 for guests and tenants', function () {
        $this->get('/profit')->assertNotFound();
        $this->actingAs(Actors::companyOwner())->get('/profit')->assertNotFound();
        expect(Route::has('admin.profit'))->toBeFalse();
    });
});

describe('DSH-02 → dashboard query budget', function () {
    it('computes the stats with a small, fixed number of queries', function () {
        $owner = Actors::companyOwner();
        seedDashboardData($owner, 3);

        $this->actingAs($owner);
        // 18 before P2-6, plus the one trip counts query (req 9.2)
        Perf::assertMaxQueries(19, fn () => $this->get(route('admin.dashboard'))->assertOk());
    });

    it('does not grow with the amount of data', function () {
        $owner = Actors::companyOwner();
        $this->actingAs($owner);

        Perf::assertConstantQueries(
            fn (int $n) => seedDashboardData($owner, $n),
            fn () => $this->get(route('admin.dashboard'))->assertOk(),
        );
    });

    it('still reports correct numbers for the tenant and ignores other companies', function () {
        $owner = Actors::companyOwner();
        seedDashboardData($owner, 2);
        seedDashboardData(Actors::companyOwner(), 3);

        $response = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk();

        // 2 per status (draft hidden for tenants) + 2 public applicants + 2 doc-holders.
        expect($response->viewData('driverStats'))->toBe([
            'total' => 16,
            'active' => 4,
            'pending' => 8,
            'inactive' => 4,
            'new_applicants' => 2,
        ]);
        expect($response->viewData('fleetStats'))->toBe(['units' => 4, 'vehicles' => 2, 'trailers' => 2]);
        expect($response->viewData('driverComplianceStats'))->toBe(['overdue' => 2, 'due_soon' => 2]);
        expect($response->viewData('fleetComplianceStats'))->toBe(['overdue' => 4, 'due_soon' => 4]);
        expect($response->viewData('maintenanceStats'))->toBe(['total_services' => 4, 'recent_30_days' => 2]);
        expect($response->original->getData())->not->toHaveKey('companyStats');
    });
});

describe('DSH-03 → draft drivers', function () {
    it('matches the tenant driver list, which hides drafts', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        DriverFactory::new()->forCompany($company)->status('active')->count(2)->create();
        DriverFactory::new()->forCompany($company)->publicDraft()->count(3)->create();

        $this->actingAs($owner);
        $dashboard = $this->get(route('admin.dashboard'))->assertOk();
        $list = $this->get(route('admin.driver.index'))->assertOk();

        expect($dashboard->viewData('driverStats')['total'])->toBe(2)
            ->and($dashboard->viewData('driverStats')['total'])->toBe($list->viewData('statusCounts')['all'])
            ->and($dashboard->viewData('driverStats')['new_applicants'])->toBe(0);
    });

});
