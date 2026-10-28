<?php

namespace Tests\Feature\Modules\TripEvents;

use App\Models\Company;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Database\Factories\TripFactory;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\Shippers\Shippers;

/**
 * Trip event fixtures (req 7.1–7.6): a trip in progress for three hours with its folder, times as
 * typed in the company's time zone, and a fuel type.
 */
final class TripEvents
{
    /**
     * The trip document types, TX and ON, a fake disk and a fuel type; returns the fuel type id.
     */
    public static function setUp(): int
    {
        Storage::fake('local');
        Shippers::seedStates();
        (new DocumentTypeSeeder)->run();

        return DB::table('fuel_types')->insertGetId(['name' => 'Diesel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function trip(Company $company, array $attributes = []): Trip
    {
        $trip = TripFactory::new()->forCompany($company)->inProgress()->create($attributes + ['started_at' => now()->subHours(3)]);
        Docs::systemFolder($trip);

        return $trip;
    }

    /**
     * A time as the dialog posts it: minutes from now, in the company's time zone.
     */
    public static function at(Company $company, int $minutesFromNow = -30): string
    {
        return CarbonImmutable::now($company->timezone ?: 'America/Chicago')->addMinutes($minutesFromNow)->format('Y-m-d\TH:i');
    }

    /**
     * The fuel dialog as posted.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function fuel(Company $company, int $fuelTypeId, array $overrides = []): array
    {
        return array_merge([
            'occurred_at' => self::at($company),
            'location_name' => "Love's #301",
            'fuel_address' => '4600 I-35',
            'fuel_city' => 'Waco',
            'fuel_state_code' => 'TX',
            'fuel_type_id' => $fuelTypeId,
            'gallons' => '120.5',
            'price_per_gallon' => '3.899',
            'total_amount' => '469.83',
            'odometer' => '182340',
        ], $overrides);
    }
}
