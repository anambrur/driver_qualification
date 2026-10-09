<?php

namespace Tests\Feature\Modules\Drivers;

use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use Database\Factories\DriverFactory;
use Illuminate\Http\UploadedFile;
use Tests\Support\Actors;

/**
 * Fixtures for the admin driver module (CRUD + 10-step wizard).
 */
final class DriverAdmin
{
    /**
     * A subscribed tenant and a driver in their company.
     *
     * @return array{0: User, 1: Driver}
     */
    public static function tenantWithDriver(array $driverAttributes = []): array
    {
        $owner = Actors::companyOwner();
        $driver = self::driverOf($owner, $driverAttributes);

        return [$owner, $driver];
    }

    public static function driverOf(User $owner, array $attributes = []): Driver
    {
        return DriverFactory::new()->forCompany(Actors::companyOf($owner))->create($attributes);
    }

    public static function companyOf(User $owner): Company
    {
        return Actors::companyOf($owner);
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
     * A valid admin create/edit (step 1) payload.
     *
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Roe',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'ssn' => '123-45-6789',
            'main_phone' => '+12025550100',
            'email' => 'jane.roe.'.uniqid().'@example.com',
            'medical_certificate_expiration_date' => now()->addYear()->toDateString(),
            'address' => '1 Main St',
            'city' => 'Austin',
            'state' => 'Texas',
            'country' => 'United States',
            'postal_code' => '73301',
            'accident' => 'no',
            'violation' => 'no',
            'denied_license' => 'no',
            'license_revoked' => 'no',
            'license_first_name' => 'Jane',
            'license_last_name' => 'Roe',
            'license_issued' => now()->subYears(5)->toDateString(),
            'license_expires' => now()->addYears(3)->toDateString(),
            'license_country' => 'United States',
            'license_state' => 'Texas',
            'license_class' => 'A',
            'license_number' => 'D1234567',
            'repeat_license_number' => 'D1234567',
            'equipment_class' => ['Straight Truck'],
            'experience' => ['no'],
        ], $overrides);
    }

    /**
     * The edit form re-posts every field, including the hidden current status.
     *
     * @return array<string, mixed>
     */
    public static function updatePayload(Driver $driver, array $overrides = []): array
    {
        return self::payload(array_merge([
            'email' => $driver->email,
            'status' => $driver->status,
        ], $overrides));
    }

    /**
     * A real PNG uploaded under any client name.
     */
    public static function png(string $clientName = 'photo.png'): UploadedFile
    {
        $fake = UploadedFile::fake()->image('real.png', 20, 20);
        $path = tempnam(sys_get_temp_dir(), 'png');
        copy($fake->getPathname(), $path);

        return new UploadedFile($path, $clientName, 'image/png', null, true);
    }
}
