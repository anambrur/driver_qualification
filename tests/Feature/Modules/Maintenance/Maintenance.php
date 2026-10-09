<?php

namespace Tests\Feature\Modules\Maintenance;

use App\Models\MaintenanceCategory;
use App\Models\User;
use App\Models\Vehicle;
use Database\Factories\MaintenanceCategoryFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Http\UploadedFile;
use Tests\Support\Actors;

/**
 * Fixtures for service logs (admin.service-log.*) and maintenance schedules
 * (admin.maintenance-schedule.*).
 */
final class Maintenance
{
    /**
     * A subscribed tenant and one vehicle in their company.
     *
     * @return array{0: User, 1: Vehicle}
     */
    public static function tenantWithVehicle(array $attributes = []): array
    {
        $owner = Actors::companyOwner();

        return [$owner, self::vehicleOf($owner, $attributes)];
    }

    public static function vehicleOf(User $owner, array $attributes = []): Vehicle
    {
        return VehicleFactory::new()->forCompany(Actors::companyOf($owner))->create($attributes);
    }

    public static function category(array $attributes = []): MaintenanceCategory
    {
        return MaintenanceCategoryFactory::new()->create($attributes);
    }

    /**
     * Headers DataTables sends ($request->ajax() checks X-Requested-With).
     *
     * @return array<string, string>
     */
    public static function ajax(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serviceLogPayload(Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $vehicle->id,
            'service_date' => now()->toDateString(),
            'maintenance_categories' => [self::category()->id],
            'maintenance_notes' => 'Oil and filter',
            'odometer_at_service' => 1000,
            'current_odometer' => 1000,
            'total_cost' => 120.5,
            'status' => 'completed',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function schedulePayload(?Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $vehicle?->id,
            'maintenance_category_id' => self::category()->id,
            'title' => 'Quarterly inspection',
            'schedule_type' => 'date',
            'interval_days' => 90,
            'status' => 'active',
        ], $overrides);
    }

    /**
     * A real file with PDF bytes, uploaded under any client name.
     */
    public static function pdf(string $clientName = 'invoice.pdf'): UploadedFile
    {
        return self::fileWith($clientName, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n", 'application/pdf');
    }

    /**
     * A real file with the given bytes, uploaded under any client name.
     */
    public static function fileWith(string $clientName, string $bytes, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $clientName, $clientMime, null, true);
    }
}
